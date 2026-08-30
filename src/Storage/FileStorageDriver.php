<?php

namespace EmirKefi\TopologyMapper\Storage;

use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Models\DataFlowPath;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;
use Illuminate\Support\Facades\File;

class FileStorageDriver implements StorageDriverInterface
{
    protected string $filePath;
    protected int $maxFlows;

    public function __construct(array $config = [])
    {
        $this->filePath = $config['file_path'] ?? storage_path('app/topology/topology-graph.json');
        $this->maxFlows = (int) ($config['max_recorded_flows'] ?? 100);
        $this->ensureDirectoryExists();
    }

    protected function ensureDirectoryExists(): void
    {
        $directory = dirname($this->filePath);
        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true, true);
        }
    }

    public function recordNode(Node $node): void
    {
        $data = $this->read();
        $nodes = $data['nodes'] ?? [];

        if (isset($nodes[$node->id])) {
            $existing = Node::fromArray($nodes[$node->id]);
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

        $data['nodes'] = $nodes;
        $this->write($data);
    }

    public function recordEdge(Edge $edge): void
    {
        $data = $this->read();
        $edges = $data['edges'] ?? [];

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

        $data['edges'] = $edges;
        $this->write($data);
    }

    public function recordFlow(DataFlowPath $flow): void
    {
        $data = $this->read();
        $flows = $data['flows'] ?? [];
        array_unshift($flows, $flow->toArray());

        if (count($flows) > $this->maxFlows) {
            $flows = array_slice($flows, 0, $this->maxFlows);
        }

        $data['flows'] = $flows;
        $this->write($data);
    }

    public function getNodes(): array
    {
        $data = $this->read();
        $nodes = [];
        foreach (($data['nodes'] ?? []) as $id => $raw) {
            $nodes[$id] = Node::fromArray($raw);
        }
        return $nodes;
    }

    public function getEdges(): array
    {
        $data = $this->read();
        $edges = [];
        foreach (($data['edges'] ?? []) as $id => $raw) {
            $edges[$id] = Edge::fromArray($raw);
        }
        return $edges;
    }

    public function getFlows(int $limit = 50): array
    {
        $data = $this->read();
        $flows = [];
        foreach (array_slice($data['flows'] ?? [], 0, $limit) as $raw) {
            $flows[] = DataFlowPath::fromArray($raw);
        }
        return $flows;
    }

    public function clear(): void
    {
        if (File::exists($this->filePath)) {
            File::delete($this->filePath);
        }
    }

    public function exportGraph(): array
    {
        $data = $this->read();

        return [
            'app_name' => config('topology.app_name', config('app.name', 'Laravel App')),
            'environment' => config('topology.environment', config('app.env', 'production')),
            'generated_at' => now()->toIso8601String(),
            'zones' => config('topology.zones', []),
            'nodes' => array_values($data['nodes'] ?? []),
            'edges' => array_values($data['edges'] ?? []),
            'flows' => $data['flows'] ?? [],
            'stats' => [
                'total_nodes' => count($data['nodes'] ?? []),
                'total_edges' => count($data['edges'] ?? []),
                'total_flows' => count($data['flows'] ?? []),
            ],
        ];
    }

    protected function read(): array
    {
        if (! File::exists($this->filePath)) {
            return ['nodes' => [], 'edges' => [], 'flows' => []];
        }

        $contents = File::get($this->filePath);
        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : ['nodes' => [], 'edges' => [], 'flows' => []];
    }

    protected function write(array $data): void
    {
        $this->ensureDirectoryExists();
        File::put($this->filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
