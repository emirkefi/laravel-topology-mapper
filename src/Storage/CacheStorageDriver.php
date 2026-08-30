<?php

namespace EmirKefi\TopologyMapper\Storage;

use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Models\DataFlowPath;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;
use Illuminate\Support\Facades\Cache;

class CacheStorageDriver implements StorageDriverInterface
{
    protected string $prefix;
    protected int $ttl;
    protected int $maxFlows;

    public function __construct(array $config = [])
    {
        $this->prefix = $config['cache_key_prefix'] ?? 'topology_mapper:';
        $this->ttl = (int) ($config['cache_ttl'] ?? 86400 * 7);
        $this->maxFlows = (int) ($config['max_recorded_flows'] ?? 100);
    }

    public function recordNode(Node $node): void
    {
        $nodes = $this->getNodesRaw();
        
        if (isset($nodes[$node->id])) {
            $existing = Node::fromArray($nodes[$node->id]);
            // Merge metadata and keep cumulative stats if updated externally
            $existing->label = $node->label;
            $existing->type = $node->type;
            $existing->zone = $node->zone;
            if ($node->host) {
                $existing->host = $node->host;
            }
            if ($node->driver) {
                $existing->driver = $node->driver;
            }
            $existing->requestCount += $node->requestCount;
            $existing->errorCount += $node->errorCount;
            $existing->latencies = array_slice(array_merge($existing->latencies, $node->latencies), -50);
            if (! empty($existing->latencies)) {
                $existing->avgLatencyMs = round(array_sum($existing->latencies) / count($existing->latencies), 2);
            }
            $existing->maxLatencyMs = max($existing->maxLatencyMs, $node->maxLatencyMs);
            $existing->metadata = array_merge($existing->metadata, $node->metadata);
            $existing->lastSeenAt = now()->toIso8601String();
            $existing->evaluateHealth();
            $nodes[$node->id] = $existing->toArray();
        } else {
            $nodes[$node->id] = $node->toArray();
        }

        Cache::put($this->prefix . 'nodes', $nodes, $this->ttl);
    }

    public function recordEdge(Edge $edge): void
    {
        $edges = $this->getEdgesRaw();

        if (isset($edges[$edge->id])) {
            $existing = Edge::fromArray($edges[$edge->id]);
            $existing->protocol = $edge->protocol;
            if ($edge->operation) {
                $existing->operation = $edge->operation;
                $existing->operations[$edge->operation] = ($existing->operations[$edge->operation] ?? 0) + 1;
            }
            $existing->requestCount += $edge->requestCount;
            $existing->errorCount += $edge->errorCount;
            $existing->latencies = array_slice(array_merge($existing->latencies, $edge->latencies), -50);
            if (! empty($existing->latencies)) {
                $existing->avgLatencyMs = round(array_sum($existing->latencies) / count($existing->latencies), 2);
            }
            $existing->maxLatencyMs = max($existing->maxLatencyMs, $edge->maxLatencyMs);
            $existing->metadata = array_merge($existing->metadata, $edge->metadata);
            $existing->lastActiveAt = now()->toIso8601String();
            $existing->evaluateHealth();
            $edges[$edge->id] = $existing->toArray();
        } else {
            $edges[$edge->id] = $edge->toArray();
        }

        Cache::put($this->prefix . 'edges', $edges, $this->ttl);
    }

    public function recordFlow(DataFlowPath $flow): void
    {
        $flows = $this->getFlowsRaw();
        array_unshift($flows, $flow->toArray());

        if (count($flows) > $this->maxFlows) {
            $flows = array_slice($flows, 0, $this->maxFlows);
        }

        Cache::put($this->prefix . 'flows', $flows, $this->ttl);
    }

    public function getNodes(): array
    {
        $raw = $this->getNodesRaw();
        $nodes = [];
        foreach ($raw as $id => $data) {
            $nodes[$id] = Node::fromArray($data);
        }
        return $nodes;
    }

    public function getEdges(): array
    {
        $raw = $this->getEdgesRaw();
        $edges = [];
        foreach ($raw as $id => $data) {
            $edges[$id] = Edge::fromArray($data);
        }
        return $edges;
    }

    public function getFlows(int $limit = 50): array
    {
        $raw = $this->getFlowsRaw();
        $flows = [];
        foreach (array_slice($raw, 0, $limit) as $data) {
            $flows[] = DataFlowPath::fromArray($data);
        }
        return $flows;
    }

    public function clear(): void
    {
        Cache::forget($this->prefix . 'nodes');
        Cache::forget($this->prefix . 'edges');
        Cache::forget($this->prefix . 'flows');
    }

    public function exportGraph(): array
    {
        return [
            'app_name' => config('topology.app_name', config('app.name', 'Laravel App')),
            'environment' => config('topology.environment', config('app.env', 'production')),
            'generated_at' => now()->toIso8601String(),
            'zones' => config('topology.zones', []),
            'nodes' => array_values($this->getNodesRaw()),
            'edges' => array_values($this->getEdgesRaw()),
            'flows' => $this->getFlowsRaw(),
            'stats' => [
                'total_nodes' => count($this->getNodesRaw()),
                'total_edges' => count($this->getEdgesRaw()),
                'total_flows' => count($this->getFlowsRaw()),
            ],
        ];
    }

    protected function getNodesRaw(): array
    {
        return Cache::get($this->prefix . 'nodes', []);
    }

    protected function getEdgesRaw(): array
    {
        return Cache::get($this->prefix . 'edges', []);
    }

    protected function getFlowsRaw(): array
    {
        return Cache::get($this->prefix . 'flows', []);
    }
}
