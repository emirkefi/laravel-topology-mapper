<?php

namespace EmirKefi\TopologyMapper\Models;

use JsonSerializable;

class Edge implements JsonSerializable
{
    public string $id;

    public function __construct(
        public string $source,
        public string $target,
        public string $protocol = 'http', // 'http', 'sql', 'redis', 'queue', 'smtp', 'storage'
        public ?string $operation = null,
        public string $status = 'healthy', // 'healthy', 'warning', 'critical'
        public int $requestCount = 0,
        public int $errorCount = 0,
        public float $avgLatencyMs = 0.0,
        public float $maxLatencyMs = 0.0,
        public array $latencies = [],
        public array $operations = [], // breakdown by operation name => count
        public array $metadata = [],
        public ?string $lastActiveAt = null,
        ?string $id = null
    ) {
        $this->id = $id ?? "{$this->source}->{$this->target}";
        $this->lastActiveAt = $this->lastActiveAt ?? now()->toIso8601String();
    }

    /**
     * Record a network / dependency traversal along this edge.
     */
    public function recordMetrics(float $durationMs, bool $isSuccess = true, ?string $operation = null): self
    {
        $this->requestCount++;
        if (! $isSuccess) {
            $this->errorCount++;
        }

        if ($operation) {
            $this->operation = $operation;
            $this->operations[$operation] = ($this->operations[$operation] ?? 0) + 1;
        }

        $this->latencies[] = round($durationMs, 2);
        if (count($this->latencies) > 50) {
            array_shift($this->latencies);
        }

        $this->avgLatencyMs = round(array_sum($this->latencies) / count($this->latencies), 2);
        $this->maxLatencyMs = max($this->maxLatencyMs, round($durationMs, 2));
        $this->lastActiveAt = now()->toIso8601String();

        $this->evaluateHealth();

        return $this;
    }

    public function getP95LatencyMs(): float
    {
        if (empty($this->latencies)) {
            return 0.0;
        }

        $sorted = $this->latencies;
        sort($sorted);
        $index = (int) ceil(count($sorted) * 0.95) - 1;
        $index = max(0, min($index, count($sorted) - 1));

        return (float) $sorted[$index];
    }

    public function getErrorRate(): float
    {
        if ($this->requestCount === 0) {
            return 0.0;
        }

        return round($this->errorCount / $this->requestCount, 4);
    }

    public function evaluateHealth(): void
    {
        $warnLatency = (float) config('topology.thresholds.latency_warning_ms', 200.0);
        $critLatency = (float) config('topology.thresholds.latency_critical_ms', 1000.0);
        $warnErrorRate = (float) config('topology.thresholds.error_rate_warning', 0.05);
        $critErrorRate = (float) config('topology.thresholds.error_rate_critical', 0.20);

        $errorRate = $this->getErrorRate();

        if ($this->avgLatencyMs >= $critLatency || $errorRate >= $critErrorRate) {
            $this->status = 'critical';
        } elseif ($this->avgLatencyMs >= $warnLatency || $errorRate >= $warnErrorRate) {
            $this->status = 'warning';
        } else {
            $this->status = 'healthy';
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            source: $data['source'],
            target: $data['target'],
            protocol: $data['protocol'] ?? 'http',
            operation: $data['operation'] ?? null,
            status: $data['status'] ?? 'healthy',
            requestCount: $data['request_count'] ?? 0,
            errorCount: $data['error_count'] ?? 0,
            avgLatencyMs: (float) ($data['avg_latency_ms'] ?? 0.0),
            maxLatencyMs: (float) ($data['max_latency_ms'] ?? 0.0),
            latencies: $data['latencies'] ?? [],
            operations: $data['operations'] ?? [],
            metadata: $data['metadata'] ?? [],
            lastActiveAt: $data['last_active_at'] ?? null,
            id: $data['id'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'target' => $this->target,
            'protocol' => $this->protocol,
            'operation' => $this->operation,
            'status' => $this->status,
            'request_count' => $this->requestCount,
            'error_count' => $this->errorCount,
            'error_rate' => $this->getErrorRate(),
            'avg_latency_ms' => $this->avgLatencyMs,
            'p95_latency_ms' => $this->getP95LatencyMs(),
            'max_latency_ms' => $this->maxLatencyMs,
            'latencies' => $this->latencies,
            'operations' => $this->operations,
            'metadata' => $this->metadata,
            'last_active_at' => $this->lastActiveAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
