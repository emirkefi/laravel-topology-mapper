<?php

namespace EmirKefi\TopologyMapper\Listeners;

use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;
use EmirKefi\TopologyMapper\Support\TraceContext;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;

class CacheEventListener
{
    protected static bool $handling = false;

    public function __construct(protected StorageDriverInterface $storage)
    {
    }

    public function handleCacheHit(CacheHit $event): void
    {
        if ($this->shouldIgnoreKey($event->key)) {
            return;
        }
        $this->recordCacheOp('Cache HIT', true);
    }

    public function handleCacheMiss(CacheMissed $event): void
    {
        if ($this->shouldIgnoreKey($event->key)) {
            return;
        }
        $this->recordCacheOp('Cache MISS', true);
    }

    public function handleKeyWritten(KeyWritten $event): void
    {
        if ($this->shouldIgnoreKey($event->key)) {
            return;
        }
        $this->recordCacheOp('Cache WRITE', true);
    }

    public function handleKeyForgotten(KeyForgotten $event): void
    {
        if ($this->shouldIgnoreKey($event->key)) {
            return;
        }
        $this->recordCacheOp('Cache FORGET', true);
    }

    protected function shouldIgnoreKey(?string $key): bool
    {
        if (! $key) {
            return false;
        }

        $prefix = config('topology.storage.cache_key_prefix', 'topology_mapper:');
        return str_starts_with($key, $prefix) 
            || str_starts_with($key, 'topology_') 
            || str_starts_with($key, 'illuminate:');
    }

    protected function recordCacheOp(string $op, bool $success): void
    {
        if (self::$handling) {
            return;
        }

        self::$handling = true;

        try {
            $cacheNodeId = 'cache:app_store';
            $node = new Node(
                id: $cacheNodeId,
                label: 'Application Cache',
                type: 'cache',
                zone: 'zone_2',
                driver: config('cache.default', 'file')
            );
            $node->recordMetrics(0.5, $success);
            $this->storage->recordNode($node);

            $sourceId = TraceContext::getOriginNodeId() ?? 'app:core';
            $edge = new Edge(
                source: $sourceId,
                target: $cacheNodeId,
                protocol: 'cache',
                operation: $op
            );
            $edge->recordMetrics(0.5, $success, $op);
            $this->storage->recordEdge($edge);
        } finally {
            self::$handling = false;
        }
    }
}
