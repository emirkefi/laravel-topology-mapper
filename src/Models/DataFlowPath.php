<?php

namespace EmirKefi\TopologyMapper\Models;

use JsonSerializable;

class DataFlowPath implements JsonSerializable
{
    public function __construct(
        public string $traceId,
        public string $originNodeId,
        public string $originLabel,
        public string $originType = 'http_route', // 'http_route', 'queue_job', 'command'
        public float $durationMs = 0.0,
        public array $hops = [], // ordered sequence of hop dictionaries
        public bool $success = true,
        public ?string $timestamp = null
    ) {
        $this->timestamp = $this->timestamp ?? now()->toIso8601String();
    }

    public function addHop(string $targetNodeId, string $protocol, string $operation, float $durationMs, bool $isSuccess = true, array $metadata = []): self
    {
        $this->hops[] = [
            'target_node_id' => $targetNodeId,
            'protocol' => $protocol,
            'operation' => $operation,
            'duration_ms' => round($durationMs, 2),
            'success' => $isSuccess,
            'timestamp' => now()->toIso8601String(),
            'metadata' => $metadata,
        ];

        if (! $isSuccess) {
            $this->success = false;
        }

        return $this;
    }

    public static function fromArray(array $data): self
    {
        return new self(
            traceId: $data['trace_id'],
            originNodeId: $data['origin_node_id'],
            originLabel: $data['origin_label'],
            originType: $data['origin_type'] ?? 'http_route',
            durationMs: (float) ($data['duration_ms'] ?? 0.0),
            hops: $data['hops'] ?? [],
            success: $data['success'] ?? true,
            timestamp: $data['timestamp'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'trace_id' => $this->traceId,
            'origin_node_id' => $this->originNodeId,
            'origin_label' => $this->originLabel,
            'origin_type' => $this->originType,
            'duration_ms' => $this->durationMs,
            'hops' => $this->hops,
            'hop_count' => count($this->hops),
            'success' => $this->success,
            'timestamp' => $this->timestamp,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
