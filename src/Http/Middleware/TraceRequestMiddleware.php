<?php

namespace EmirKefi\TopologyMapper\Http\Middleware;

use Closure;
use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Models\DataFlowPath;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;
use EmirKefi\TopologyMapper\Support\TraceContext;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TraceRequestMiddleware
{
    public function __construct(protected StorageDriverInterface $storage)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('topology.enabled', true)) {
            return $next($request);
        }

        // Determine controller / action or URI label
        $route = $request->route();
        $actionName = $route ? $route->getActionName() : null;
        $method = $request->method();
        $uri = $request->path();

        $cleanPath = '/' . ltrim($uri, '/');

        if ($actionName && $actionName !== 'Closure') {
            $controllerLabel = class_basename($actionName);
            $originId = "controller:" . str_replace(['@', '\\'], ['.', '_'], $actionName);
            $originLabel = "{$method} {$cleanPath} ({$controllerLabel})";
        } else {
            $originId = "route:{$method}:{$cleanPath}";
            $originLabel = "{$method} {$cleanPath}";
        }

        // Don't trace internal dashboard polling routes to avoid noise
        $dashboardPath = config('topology.dashboard.path', 'topology');
        if (str_starts_with($uri, $dashboardPath)) {
            return $next($request);
        }

        // Start trace
        TraceContext::start($originId, $originLabel, 'http_route');

        // Record Controller Node (Zone 0 Backbone)
        $node = new Node(
            id: $originId,
            label: $originLabel,
            type: 'app',
            zone: 'zone_0',
            driver: 'controller',
            metadata: [
                'method' => $method,
                'path' => $cleanPath,
                'action' => $actionName,
            ]
        );
        $this->storage->recordNode($node);

        // Record link from App Core to Controller
        $coreEdge = new Edge(
            source: 'app:core',
            target: $originId,
            protocol: 'http',
            operation: "Route {$method} {$cleanPath}"
        );
        $this->storage->recordEdge($coreEdge);

        $startTime = microtime(true);

        try {
            $response = $next($request);
            $durationMs = (microtime(true) - $startTime) * 1000;
            $isSuccess = $response->isSuccessful() || $response->isRedirection();

            $node->recordMetrics($durationMs, $isSuccess);
            $this->storage->recordNode($node);
            $coreEdge->recordMetrics($durationMs, $isSuccess);
            $this->storage->recordEdge($coreEdge);

            // Record flow trace
            $hops = TraceContext::getHops();
            if (empty($hops)) {
                $hops = [
                    [
                        'target_node_id' => $originId,
                        'protocol' => 'http',
                        'operation' => "{$method} {$cleanPath}",
                        'duration_ms' => round($durationMs, 2),
                        'success' => $isSuccess,
                        'timestamp' => now()->toIso8601String(),
                        'metadata' => [],
                    ]
                ];
            }

            $flow = new DataFlowPath(
                traceId: TraceContext::getTraceId() ?? uniqid('trace_'),
                originNodeId: $originId,
                originLabel: $originLabel,
                originType: 'http_route',
                durationMs: round($durationMs, 2),
                hops: $hops,
                success: $isSuccess
            );
            $this->storage->recordFlow($flow);

            return $response;
        } catch (\Throwable $e) {
            $durationMs = (microtime(true) - $startTime) * 1000;
            $node->recordMetrics($durationMs, false);
            $this->storage->recordNode($node);

            $hops = TraceContext::getHops();
            if (empty($hops)) {
                $hops = [
                    [
                        'target_node_id' => $originId,
                        'protocol' => 'http',
                        'operation' => "{$method} {$cleanPath} (Failed)",
                        'duration_ms' => round($durationMs, 2),
                        'success' => false,
                        'timestamp' => now()->toIso8601String(),
                        'metadata' => [],
                    ]
                ];
            }

            $flow = new DataFlowPath(
                traceId: TraceContext::getTraceId() ?? uniqid('trace_'),
                originNodeId: $originId,
                originLabel: $originLabel,
                originType: 'http_route',
                durationMs: round($durationMs, 2),
                hops: $hops,
                success: false
            );
            $this->storage->recordFlow($flow);

            throw $e;
        } finally {
            TraceContext::reset();
        }
    }
}
