<?php

namespace EmirKefi\TopologyMapper\Models;

use JsonSerializable;

class Node implements JsonSerializable
{
    public function __construct(
        public string $id,
        public string $label,
        public string $type, // 'app', 'database', 'redis', 'cache', 'queue', 'worker', 'external_api', 'mail', 'storage'
        public string $zone = 'zone_0', // 'zone_0', 'zone_1', 'zone_2', 'zone_3', 'zone_4'
        public ?string $host = null,
        public ?string $driver = null,
        public string $status = 'healthy', // 'healthy', 'warning', 'critical'
        public int $requestCount = 0,
        public int $errorCount = 0,
        public float $avgLatencyMs = 0.0,
        public float $maxLatencyMs = 0.0,
        public array $latencies = [],
        public array $metadata = [],
        public ?string $lastSeenAt = null
    ) {
        $this->lastSeenAt = $this->lastSeenAt ?? now()->toIso8601String();
    }

    /**
     * Record a call or interaction with this node.
     */
    public function recordMetrics(float $durationMs, bool $isSuccess = true): self
    {
        $this->requestCount++;
        if (! $isSuccess) {
            $this->errorCount++;
        }

        // Rolling latency sample (keep last 50 samples for percentile calculations)
        $this->latencies[] = round($durationMs, 2);
        if (count($this->latencies) > 50) {
            array_shift($this->latencies);
        }

        $this->avgLatencyMs = round(array_sum($this->latencies) / count($this->latencies), 2);
        $this->maxLatencyMs = max($this->maxLatencyMs, round($durationMs, 2));
        $this->lastSeenAt = now()->toIso8601String();

        $this->evaluateHealth();

        return $this;
    }

    /**
     * Calculate 95th percentile latency.
     */
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

    /**
     * Calculate error rate percentage (0.0 to 1.0).
     */
    public function getErrorRate(): float
    {
        if ($this->requestCount === 0) {
            return 0.0;
        }

        return round($this->errorCount / $this->requestCount, 4);
    }

    /**
     * Evaluate node status according to latency and error rate thresholds.
     */
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
            id: $data['id'],
            label: $data['label'],
            type: $data['type'],
            zone: $data['zone'] ?? 'zone_0',
            host: $data['host'] ?? null,
            driver: $data['driver'] ?? null,
            status: $data['status'] ?? 'healthy',
            requestCount: $data['request_count'] ?? 0,
            errorCount: $data['error_count'] ?? 0,
            avgLatencyMs: (float) ($data['avg_latency_ms'] ?? 0.0),
            maxLatencyMs: (float) ($data['max_latency_ms'] ?? 0.0),
            latencies: $data['latencies'] ?? [],
            metadata: $data['metadata'] ?? [],
            lastSeenAt: $data['last_seen_at'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'type' => $this->type,
            'zone' => $this->zone,
            'host' => $this->host,
            'driver' => $this->driver,
            'status' => $this->status,
            'request_count' => $this->requestCount,
            'error_count' => $this->errorCount,
            'error_rate' => $this->getErrorRate(),
            'avg_latency_ms' => $this->avgLatencyMs,
            'p95_latency_ms' => $this->getP95LatencyMs(),
            'max_latency_ms' => $this->maxLatencyMs,
            'latencies' => $this->latencies,
            'metadata' => $this->metadata,
            'last_seen_at' => $this->lastSeenAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
