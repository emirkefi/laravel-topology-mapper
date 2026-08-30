<?php

namespace EmirKefi\TopologyMapper\Listeners;

use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;
use EmirKefi\TopologyMapper\Support\TraceContext;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Support\Str;

class HttpClientListener
{
    /** @var array<string, float> */
    protected static array $startTimes = [];

    public function __construct(protected StorageDriverInterface $storage)
    {
    }

    public function handleRequestSending(RequestSending $event): void
    {
        $requestId = spl_object_hash($event->request);
        self::$startTimes[$requestId] = microtime(true);
    }

    public function handleResponseReceived(ResponseReceived $event): void
    {
        $requestId = spl_object_hash($event->request);
        $startTime = self::$startTimes[$requestId] ?? null;
        unset(self::$startTimes[$requestId]);

        $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : 0.0;
        $url = $event->request->url();
        $host = parse_url($url, PHP_URL_HOST) ?? 'external-api';

        if ($this->shouldIgnore($host)) {
            return;
        }

        $method = strtoupper($event->request->method());
        $status = $event->response->status();
        $isSuccess = $event->response->successful();

        $this->recordTraffic($host, $method, $url, $durationMs, $isSuccess, $status);
    }

    public function handleConnectionFailed(ConnectionFailed $event): void
    {
        $requestId = spl_object_hash($event->request);
        $startTime = self::$startTimes[$requestId] ?? null;
        unset(self::$startTimes[$requestId]);

        $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : 0.0;
        $url = $event->request->url();
        $host = parse_url($url, PHP_URL_HOST) ?? 'external-api';

        if ($this->shouldIgnore($host)) {
            return;
        }

        $method = strtoupper($event->request->method());
        $this->recordTraffic($host, $method, $url, $durationMs, false, 0, 'Connection Failed');
    }

    protected function recordTraffic(string $host, string $method, string $url, float $durationMs, bool $isSuccess, int $statusCode, ?string $errorMsg = null): void
    {
        $nodeId = "http:{$host}";
        $label = $this->friendlyHostLabel($host);

        // 1. Record Node (Zone 4 External API)
        $node = new Node(
            id: $nodeId,
            label: $label,
            type: 'external_api',
            zone: 'zone_4',
            host: $host,
            driver: 'http',
            metadata: [
                'latest_endpoint' => parse_url($url, PHP_URL_PATH) ?? '/',
                'last_status_code' => $statusCode,
            ]
        );
        $node->recordMetrics($durationMs, $isSuccess);
        $this->storage->recordNode($node);

        // 2. Record Edge from Origin to External Node
        $sourceId = TraceContext::getOriginNodeId() ?? 'app:core';
        $operation = "{$method} " . (parse_url($url, PHP_URL_PATH) ?? '/');

        $edge = new Edge(
            source: $sourceId,
            target: $nodeId,
            protocol: 'http',
            operation: $operation,
            metadata: [
                'last_status' => $statusCode,
                'error' => $errorMsg,
            ]
        );
        $edge->recordMetrics($durationMs, $isSuccess, $operation);
        $this->storage->recordEdge($edge);

        // 3. Add Hop to Active Trace
        TraceContext::addHop($nodeId, 'http', $operation, $durationMs, $isSuccess, [
            'status' => $statusCode,
            'url' => $url,
        ]);
    }

    protected function shouldIgnore(string $host): bool
    {
        $ignoredHosts = config('topology.ignore.hosts', ['127.0.0.1', 'localhost']);
        foreach ($ignoredHosts as $ignored) {
            if (str_contains($host, $ignored)) {
                return true;
            }
        }
        return false;
    }

    protected function friendlyHostLabel(string $host): string
    {
        // Give recognizable labels to popular APIs
        if (str_contains($host, 'stripe.com')) return 'Stripe API (' . $host . ')';
        if (str_contains($host, 'openai.com')) return 'OpenAI API (' . $host . ')';
        if (str_contains($host, 'github.com')) return 'GitHub API (' . $host . ')';
        if (str_contains($host, 'amazonaws.com')) return 'AWS Service (' . $host . ')';
        if (str_contains($host, 'mailgun.net') || str_contains($host, 'mailgun.org')) return 'Mailgun API (' . $host . ')';
        if (str_contains($host, 'sendgrid.net')) return 'SendGrid API (' . $host . ')';
        if (str_contains($host, 'postmarkapp.com')) return 'Postmark API (' . $host . ')';

        return "External API ({$host})";
    }
}
