<?php

namespace EmirKefi\TopologyMapper\Support;

use Illuminate\Support\Str;

class TraceContext
{
    protected static ?string $currentTraceId = null;
    protected static ?string $currentOriginNodeId = null;
    protected static ?string $currentOriginLabel = null;
    protected static ?string $currentOriginType = null; // 'http_route', 'queue_job', 'artisan_command'
    protected static array $currentHops = [];
    protected static float $startTime = 0.0;

    /**
     * Start a new trace context for an incoming HTTP request, queue worker job, or CLI command.
     */
    public static function start(string $originNodeId, string $originLabel, string $originType = 'http_route'): string
    {
        self::$currentTraceId = (string) Str::uuid();
        self::$currentOriginNodeId = $originNodeId;
        self::$currentOriginLabel = $originLabel;
        self::$currentOriginType = $originType;
        self::$currentHops = [];
        self::$startTime = microtime(true);

        return self::$currentTraceId;
    }

    /**
     * Resume a trace context from an existing trace ID (e.g. across queue job boundaries).
     */
    public static function resume(string $traceId, string $originNodeId, string $originLabel, string $originType = 'queue_job'): void
    {
        self::$currentTraceId = $traceId;
        self::$currentOriginNodeId = $originNodeId;
        self::$currentOriginLabel = $originLabel;
        self::$currentOriginType = $originType;
    }

    /**
     * Add a hop (outgoing call or interaction) to the current trace.
     */
    public static function addHop(string $targetNodeId, string $protocol, string $operation, float $durationMs, bool $isSuccess = true, array $metadata = []): void
    {
        if (! self::$currentTraceId) {
            return;
        }

        self::$currentHops[] = [
            'target_node_id' => $targetNodeId,
            'protocol' => $protocol,
            'operation' => $operation,
            'duration_ms' => round($durationMs, 2),
            'success' => $isSuccess,
            'timestamp' => microtime(true),
            'metadata' => $metadata,
        ];
    }

    public static function getTraceId(): ?string
    {
        return self::$currentTraceId;
    }

    public static function getOriginNodeId(): ?string
    {
        return self::$currentOriginNodeId;
    }

    public static function getOriginLabel(): ?string
    {
        return self::$currentOriginLabel;
    }

    public static function getOriginType(): ?string
    {
        return self::$currentOriginType;
    }

    public static function getHops(): array
    {
        return self::$currentHops;
    }

    public static function getDurationMs(): float
    {
        if (self::$startTime <= 0) {
            return 0.0;
        }
        return round((microtime(true) - self::$startTime) * 1000, 2);
    }

    /**
     * Reset the active trace context.
     */
    public static function reset(): void
    {
        self::$currentTraceId = null;
        self::$currentOriginNodeId = null;
        self::$currentOriginLabel = null;
        self::$currentOriginType = null;
        self::$currentHops = [];
        self::$startTime = 0.0;
    }
}
