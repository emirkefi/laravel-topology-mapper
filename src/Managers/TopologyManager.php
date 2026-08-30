<?php

namespace EmirKefi\TopologyMapper\Managers;

use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Listeners\CacheEventListener;
use EmirKefi\TopologyMapper\Listeners\DatabaseQueryListener;
use EmirKefi\TopologyMapper\Listeners\HttpClientListener;
use EmirKefi\TopologyMapper\Listeners\MailEventListener;
use EmirKefi\TopologyMapper\Listeners\QueueLifecycleListener;
use EmirKefi\TopologyMapper\Listeners\RedisCommandListener;
use EmirKefi\TopologyMapper\Models\DataFlowPath;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;
use EmirKefi\TopologyMapper\Scanners\StaticConfigScanner;
use EmirKefi\TopologyMapper\Storage\CacheStorageDriver;
use EmirKefi\TopologyMapper\Storage\FileStorageDriver;
use EmirKefi\TopologyMapper\Storage\MemoryStorageDriver;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Cache\Events\KeyForgotten;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Client\Events\ConnectionFailed;
use Illuminate\Http\Client\Events\RequestSending;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Redis\Events\CommandExecuted;
use Illuminate\Support\Facades\Event;

class TopologyManager
{
    protected StorageDriverInterface $storage;
    protected array $config;
    protected bool $booted = false;

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->storage = $this->resolveStorageDriver($config['storage'] ?? []);
    }

    public function getStorage(): StorageDriverInterface
    {
        return $this->storage;
    }

    public function setStorage(StorageDriverInterface $storage): self
    {
        $this->storage = $storage;
        return $this;
    }

    /**
     * Resolve the requested storage driver.
     */
    protected function resolveStorageDriver(array $storageConfig): StorageDriverInterface
    {
        $driver = $storageConfig['driver'] ?? 'cache';

        return match ($driver) {
            'file' => new FileStorageDriver($storageConfig),
            'array', 'memory' => new MemoryStorageDriver($storageConfig),
            default => new CacheStorageDriver($storageConfig),
        };
    }

    /**
     * Initialize static configuration scanning and runtime event interceptors.
     */
    public function bootInterceptors(): void
    {
        if ($this->booted || ! ($this->config['enabled'] ?? true)) {
            return;
        }

        // 1. Initial Static Scan
        $this->runStaticScan();

        $interceptors = $this->config['interceptors'] ?? [];

        // 2. HTTP Client Listener
        if ($interceptors['http'] ?? true) {
            $httpListener = new HttpClientListener($this->storage);
            Event::listen(RequestSending::class, [$httpListener, 'handleRequestSending']);
            Event::listen(ResponseReceived::class, [$httpListener, 'handleResponseReceived']);
            Event::listen(ConnectionFailed::class, [$httpListener, 'handleConnectionFailed']);
        }

        // 3. Database Query Listener
        if ($interceptors['database'] ?? true) {
            $dbListener = new DatabaseQueryListener($this->storage);
            if (class_exists(\Illuminate\Support\Facades\DB::class)) {
                \Illuminate\Support\Facades\DB::listen(function (QueryExecuted $query) use ($dbListener) {
                    $dbListener->handle($query);
                });
            } else {
                Event::listen(QueryExecuted::class, [$dbListener, 'handle']);
            }
        }

        // 4. Redis Command Listener
        if ($interceptors['redis'] ?? true) {
            $redisListener = new RedisCommandListener($this->storage);
            Event::listen(CommandExecuted::class, [$redisListener, 'handle']);
        }

        // 5. Queue Lifecycle Listener
        if ($interceptors['queue'] ?? true) {
            $queueListener = new QueueLifecycleListener($this->storage);
            Event::listen(JobQueued::class, [$queueListener, 'handleJobQueued']);
            Event::listen(JobProcessing::class, [$queueListener, 'handleJobProcessing']);
            Event::listen(JobProcessed::class, [$queueListener, 'handleJobProcessed']);
            Event::listen(JobFailed::class, [$queueListener, 'handleJobFailed']);
        }

        // 6. Cache Event Listener
        if ($interceptors['cache'] ?? true) {
            $cacheListener = new CacheEventListener($this->storage);
            Event::listen(CacheHit::class, [$cacheListener, 'handleCacheHit']);
            Event::listen(CacheMissed::class, [$cacheListener, 'handleCacheMiss']);
            Event::listen(KeyWritten::class, [$cacheListener, 'handleKeyWritten']);
            Event::listen(KeyForgotten::class, [$cacheListener, 'handleKeyForgotten']);
        }

        // 7. Mail Event Listener
        if ($interceptors['mail'] ?? true) {
            $mailListener = new MailEventListener($this->storage);
            Event::listen(MessageSent::class, [$mailListener, 'handleMessageSent']);
        }

        $this->booted = true;
    }

    /**
     * Run static configuration scanner and merge defined nodes/edges into storage.
     */
    public function runStaticScan(): array
    {
        $scanner = new StaticConfigScanner();
        $scanResults = $scanner->scan();

        foreach ($scanResults['nodes'] as $node) {
            $this->storage->recordNode($node);
        }

        foreach ($scanResults['edges'] as $edge) {
            $this->storage->recordEdge($edge);
        }

        return $scanResults;
    }

    /**
     * Generate enriched graph payload with bottlenecks, health metrics, and zone groups.
     */
    public function getEnrichedGraph(): array
    {
        $nodes = array_values($this->storage->getNodes());
        $edges = array_values($this->storage->getEdges());
        $flows = $this->storage->getFlows();

        $warnLatency = (float) ($this->config['thresholds']['latency_warning_ms'] ?? 200.0);
        $critLatency = (float) ($this->config['thresholds']['latency_critical_ms'] ?? 1000.0);

        $bottlenecks = [];
        $healthyCount = 0;
        $warningCount = 0;
        $criticalCount = 0;

        foreach ($nodes as $node) {
            if ($node->status === 'critical') {
                $criticalCount++;
                $bottlenecks[] = [
                    'type' => 'node',
                    'id' => $node->id,
                    'label' => $node->label,
                    'zone' => $node->zone,
                    'avg_latency_ms' => $node->avgLatencyMs,
                    'error_rate' => $node->getErrorRate(),
                    'reason' => $node->avgLatencyMs >= $critLatency
                        ? "Critical Latency: {$node->avgLatencyMs}ms (Threshold: {$critLatency}ms)"
                        : "High Error Rate: " . round($node->getErrorRate() * 100, 1) . "%",
                ];
            } elseif ($node->status === 'warning') {
                $warningCount++;
                $bottlenecks[] = [
                    'type' => 'node',
                    'id' => $node->id,
                    'label' => $node->label,
                    'zone' => $node->zone,
                    'avg_latency_ms' => $node->avgLatencyMs,
                    'error_rate' => $node->getErrorRate(),
                    'reason' => "Warning Latency: {$node->avgLatencyMs}ms (Threshold: {$warnLatency}ms)",
                ];
            } else {
                $healthyCount++;
            }
        }

        foreach ($edges as $edge) {
            if ($edge->status === 'critical' || $edge->status === 'warning') {
                $bottlenecks[] = [
                    'type' => 'edge',
                    'id' => $edge->id,
                    'label' => "{$edge->source} ➔ {$edge->target}",
                    'avg_latency_ms' => $edge->avgLatencyMs,
                    'reason' => "Slow Edge Route: {$edge->avgLatencyMs}ms ({$edge->protocol})",
                ];
            }
        }

        // Compute overall system health grade
        $totalNodes = max(1, count($nodes));
        $healthScore = round((($healthyCount + ($warningCount * 0.5)) / $totalNodes) * 100);

        return [
            'app_name' => $this->config['app_name'] ?? config('app.name', 'Laravel App'),
            'environment' => $this->config['environment'] ?? config('app.env', 'production'),
            'generated_at' => now()->toIso8601String(),
            'zones' => $this->config['zones'] ?? [],
            'nodes' => array_map(fn (Node $n) => $n->toArray(), $nodes),
            'edges' => array_map(fn (Edge $e) => $e->toArray(), $edges),
            'flows' => array_map(fn (DataFlowPath $f) => $f->toArray(), $flows),
            'health' => [
                'score' => $healthScore,
                'status' => $healthScore >= 90 ? 'optimal' : ($healthScore >= 70 ? 'degraded' : 'critical'),
                'healthy_nodes' => $healthyCount,
                'warning_nodes' => $warningCount,
                'critical_nodes' => $criticalCount,
            ],
            'bottlenecks' => $bottlenecks,
            'summary' => [
                'total_nodes' => count($nodes),
                'total_edges' => count($edges),
                'total_flows' => count($flows),
            ],
        ];
    }

    /**
     * Clear topology records.
     */
    public function clear(): void
    {
        $this->storage->clear();
        $this->runStaticScan();
    }
}
