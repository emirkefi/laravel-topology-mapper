<?php

namespace EmirKefi\TopologyMapper\Storage;

use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Models\DataFlowPath;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;

class MemoryStorageDriver implements StorageDriverInterface
{
    /** @var array<string, Node> */
    protected array $nodes = [];

    /** @var array<string, Edge> */
    protected array $edges = [];

    /** @var array<int, DataFlowPath> */
    protected array $flows = [];

    protected int $maxFlows;

    public function __construct(array $config = [])
    {
        $this->maxFlows = (int) ($config['max_recorded_flows'] ?? 100);
    }

    public function recordNode(Node $node): void
    {
        if (isset($this->nodes[$node->id])) {
            $existing = $this->nodes[$node->id];
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
            $this->nodes[$node->id] = $existing;
        } else {
            $this->nodes[$node->id] = $node;
        }
    }

    public function recordEdge(Edge $edge): void
    {
        if (isset($this->edges[$edge->id])) {
            $existing = $this->edges[$edge->id];
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
            $this->edges[$edge->id] = $existing;
        } else {
            $this->edges[$edge->id] = $edge;
        }
    }

    public function recordFlow(DataFlowPath $flow): void
    {
        array_unshift($this->flows, $flow);
        if (count($this->flows) > $this->maxFlows) {
            $this->flows = array_slice($this->flows, 0, $this->maxFlows);
        }
    }

    public function getNodes(): array
    {
        return $this->nodes;
    }

    public function getEdges(): array
    {
        return $this->edges;
    }

    public function getFlows(int $limit = 50): array
    {
        return array_slice($this->flows, 0, $limit);
    }

    public function clear(): void
    {
        $this->nodes = [];
        $this->edges = [];
        $this->flows = [];
    }

    public function exportGraph(): array
    {
        $nodesRaw = array_map(fn (Node $n) => $n->toArray(), array_values($this->nodes));
        $edgesRaw = array_map(fn (Edge $e) => $e->toArray(), array_values($this->edges));
        $flowsRaw = array_map(fn (DataFlowPath $f) => $f->toArray(), array_values($this->flows));

        return [
            'app_name' => config('topology.app_name', config('app.name', 'Laravel App')),
            'environment' => config('topology.environment', config('app.env', 'production')),
            'generated_at' => now()->toIso8601String(),
            'zones' => config('topology.zones', []),
            'nodes' => $nodesRaw,
            'edges' => $edgesRaw,
            'flows' => $flowsRaw,
            'stats' => [
                'total_nodes' => count($this->nodes),
                'total_edges' => count($this->edges),
                'total_flows' => count($this->flows),
            ],
        ];
    }
}
