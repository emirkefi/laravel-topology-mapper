<?php

namespace EmirKefi\TopologyMapper\Listeners;

use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;
use EmirKefi\TopologyMapper\Support\TraceContext;
use Illuminate\Redis\Events\CommandExecuted;

class RedisCommandListener
{
    protected static bool $handling = false;

    public function __construct(protected StorageDriverInterface $storage)
    {
    }

    public function handle(CommandExecuted $event): void
    {
        if (self::$handling) {
            return;
        }

        // Ignore internal topology mapper redis cache entries
        $prefix = config('topology.storage.cache_key_prefix', 'topology_mapper:');
        foreach ($event->parameters as $param) {
            if (is_string($param) && (str_contains($param, $prefix) || str_contains($param, 'topology_'))) {
                return;
            }
        }

        self::$handling = true;

        try {
            $connName = $event->connectionName ?? 'default';
            $redisNodeId = "redis:{$connName}";

            $durationMs = (float) $event->time; // Redis event time is in ms
            $command = strtoupper($event->command);
            $operation = "REDIS {$command}";

            // 1. Record Redis Node (Zone 2)
            $node = new Node(
                id: $redisNodeId,
                label: "Redis ({$connName})",
                type: 'redis',
                zone: 'zone_2',
                host: config("database.redis.{$connName}.host", '127.0.0.1'),
                driver: 'redis',
                metadata: [
                    'connection' => $connName,
                ]
            );
            $node->recordMetrics($durationMs, true);
            $this->storage->recordNode($node);

            // 2. Record Edge
            $sourceId = TraceContext::getOriginNodeId() ?? 'app:core';
            $edge = new Edge(
                source: $sourceId,
                target: $redisNodeId,
                protocol: 'redis',
                operation: $operation
            );
            $edge->recordMetrics($durationMs, true, $operation);
            $this->storage->recordEdge($edge);

            // 3. Add Hop to Active Trace
            TraceContext::addHop($redisNodeId, 'redis', $operation, $durationMs, true, [
                'command' => $command,
            ]);
        } finally {
            self::$handling = false;
        }
    }
}
