<?php

namespace EmirKefi\TopologyMapper\Services;

use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;

class PerformanceDoctor
{
    /**
     * Diagnose a specific node and generate tailored performance solutions.
     *
     * @param Node $node
     * @param float $warnLatency
     * @param float $critLatency
     * @return array
     */
    public static function diagnoseNode(Node $node, float $warnLatency = 200.0, float $critLatency = 1000.0): array
    {
        $recommendations = [];
        $avg = $node->avgLatencyMs;
        $max = $node->maxLatencyMs;
        $errorRate = $node->getErrorRate();

        // 1. Database Tier Diagnosis (Area 1 / SQL)
        if ($node->type === 'database' || $node->zone === 'zone_1') {
            if ($avg >= $warnLatency || $max >= $critLatency) {
                $recommendations[] = [
                    'severity' => $avg >= $critLatency ? 'CRITICAL' : 'HIGH',
                    'title' => 'Missing Database Index or Table Full-Scan Detected',
                    'category' => 'Database Optimization',
                    'symptom' => "Average query latency is {$avg}ms (Max: {$max}ms), exceeding the {$warnLatency}ms warning threshold.",
                    'solution' => 'Analyze slow queries using EXPLAIN, verify foreign key indexes, and prevent N+1 queries by eager loading Eloquent relationships.',
                    'code_snippet' => "// 1. Eager load relationships to fix N+1:\n\$orders = Order::with(['user', 'items.product'])->latest()->paginate(20);\n\n// 2. Add Composite Index in migration:\n\$table->index(['user_id', 'created_at']);\n\n// 3. Cache repetitive query results:\n\$stats = Cache::remember('dashboard:stats', 3600, fn () => Order::calculateMetrics());",
                    'docs_link' => 'https://laravel.com/docs/eloquent-relationships#eager-loading',
                ];

                if ($node->requestCount > 50) {
                    $recommendations[] = [
                        'severity' => 'MEDIUM',
                        'title' => 'High Database Read Load - Implement Read Replicas & Connection Pooling',
                        'category' => 'Infrastructure Scaling',
                        'symptom' => "High traffic density detected ({$node->requestCount} queries recorded on primary database).",
                        'solution' => 'Configure read/write separation in config/database.php to offload SELECT queries to dedicated read replicas.',
                        'code_snippet' => "// config/database.php\n'mysql' => [\n    'read' => [\n        'host' => [env('DB_READ_HOST_1', '192.168.1.1')],\n    ],\n    'write' => [\n        'host' => [env('DB_WRITE_HOST', '192.168.1.2')],\n    ],\n    'sticky' => true,\n    'driver' => 'mysql',\n],",
                        'docs_link' => 'https://laravel.com/docs/database#read-and-write-connections',
                    ];
                }
            }
        }

        // 2. External Autonomous Systems & HTTP APIs (Area 4)
        elseif ($node->type === 'external_api' || $node->zone === 'zone_4') {
            if ($avg >= $warnLatency) {
                $recommendations[] = [
                    'severity' => $avg >= $critLatency ? 'CRITICAL' : 'HIGH',
                    'title' => 'Synchronous External HTTP API Bottleneck',
                    'category' => 'Async & Concurrency',
                    'symptom' => "External HTTP service '{$node->label}' is taking {$avg}ms per request, blocking PHP worker processes.",
                    'solution' => 'Offload slow 3rd-party API calls to background queues, set strict connection timeouts, or use concurrent HTTP pooling.',
                    'code_snippet' => "// 1. Use HTTP Client with strict timeout & retry backoff:\n\$response = Http::timeout(3)\n    ->retry(2, 100, throw: false)\n    ->post('{$node->host}/endpoint', \$data);\n\n// 2. Execute parallel requests with Http::pool:\n\$responses = Http::pool(fn (Pool \$pool) => [\n    \$pool->as('primary')->timeout(2)->get('{$node->host}/data1'),\n    \$pool->as('backup')->timeout(2)->get('{$node->host}/data2'),\n]);\n\n// 3. Dispatch to background queue instead of blocking user request:\nProcessExternalSyncJob::dispatch(\$payload)->onQueue('integrations');",
                    'docs_link' => 'https://laravel.com/docs/http-client#concurrent-requests',
                ];
            }

            if ($errorRate > 0.05) {
                $recommendations[] = [
                    'severity' => 'CRITICAL',
                    'title' => 'External Dependency Instability / Circuit Breaker Recommended',
                    'category' => 'Resilience & Fault Tolerance',
                    'symptom' => "Failure rate of " . round($errorRate * 100, 1) . "% detected for endpoint '{$node->label}'.",
                    'solution' => 'Implement fallback graceful degradation or a Circuit Breaker to prevent cascading failures from taking down the core application.',
                    'code_snippet' => "// Fallback pattern with cache fallback:\nreturn Cache::remember('api_fallback:{$node->id}', 300, function () use (\$endpoint) {\n    try {\n        return Http::timeout(2)->get(\$endpoint)->throw()->json();\n    } catch (\Throwable \$e) {\n        Log::warning('3rd party API down, serving cached fallback');\n        return config('services.defaults.response');\n    }\n});",
                    'docs_link' => 'https://laravel.com/docs/http-client#error-handling',
                ];
            }
        }

        // 3. Redis & Cache Tier Diagnosis (Area 2)
        elseif ($node->type === 'redis' || $node->type === 'cache' || $node->zone === 'zone_2') {
            if ($avg >= 20.0 || $max >= 100.0) {
                $recommendations[] = [
                    'severity' => 'HIGH',
                    'title' => 'Redis/Cache Latency Anomaly Detected',
                    'category' => 'In-Memory Caching',
                    'symptom' => "In-memory store is taking {$avg}ms (expected < 5ms). This indicates large payload serialization, slow network roundtrips, or key thrashing.",
                    'solution' => 'Enable Redis pipeline execution for batch operations, compress large stored payloads, or switch cache serialization to igbinary.',
                    'code_snippet' => "// 1. Batch multiple Redis commands with Pipelines:\nRedis::pipeline(function (\$pipe) use (\$items) {\n    foreach (\$items as \$key => \$val) {\n        \$pipe->set(\"item:{\$key}\", \$val, 'EX', 3600);\n    }\n});\n\n// 2. Enable persistent connections in config/database.php:\n'redis' => [\n    'options' => [\n        'persistent' => true,\n    ],\n],",
                    'docs_link' => 'https://laravel.com/docs/redis#pipelining-commands',
                ];
            }
        }

        // 4. Async & Queue Tier Diagnosis (Area 3)
        elseif ($node->type === 'queue' || $node->type === 'worker' || $node->zone === 'zone_3') {
            if ($node->driver === 'sync') {
                $recommendations[] = [
                    'severity' => 'HIGH',
                    'title' => 'Synchronous Queue Driver in Use',
                    'category' => 'Queue Architecture',
                    'symptom' => "Jobs dispatched to queue '{$node->label}' are executing synchronously in the HTTP request thread.",
                    'solution' => "Switch QUEUE_CONNECTION from 'sync' to 'redis' or 'sqs' to free up web worker processes.",
                    'code_snippet' => "// In your .env file:\nQUEUE_CONNECTION=redis\n\n// Run background queue worker in production via Supervisor:\n// php artisan queue:work redis --sleep=3 --tries=3 --max-time=3600",
                    'docs_link' => 'https://laravel.com/docs/queues#driver-prerequisites',
                ];
            }

            if ($avg >= $warnLatency) {
                $recommendations[] = [
                    'severity' => 'MEDIUM',
                    'title' => 'Queue Job Processing Bottleneck',
                    'category' => 'Worker Scalability',
                    'symptom' => "Average job lifecycle duration is {$avg}ms. Workers may experience concurrency starvation under peak load.",
                    'solution' => 'Chunk large batch jobs into smaller sub-tasks, increase worker process count, or set up Laravel Horizon auto-scaling.',
                    'code_snippet' => "// Chunk heavy Eloquent updates in queued jobs:\nUser::chunkById(500, function (\$users) {\n    foreach (\$users as \$user) {\n        // Lightweight queued processing\n    }\n});",
                    'docs_link' => 'https://laravel.com/docs/horizon',
                ];
            }
        }

        // 5. Zone 0 Backbone (Controllers & HTTP Routes)
        elseif ($node->type === 'app' || $node->zone === 'zone_0') {
            if ($avg >= $warnLatency) {
                $recommendations[] = [
                    'severity' => $avg >= $critLatency ? 'CRITICAL' : 'HIGH',
                    'title' => 'Heavy Controller Endpoint Response Time',
                    'category' => 'HTTP Routing & Caching',
                    'symptom' => "Route '{$node->label}' takes {$avg}ms to execute from request to response.",
                    'solution' => 'Cache full HTTP responses with HTTP Cache-Control or spatie/laravel-responsecache, defer heavy computation to events, or optimize database queries executed during the route lifecycle.',
                    'code_snippet' => "// 1. Add Response Cache Headers:\nreturn response()->json(\$data)\n    ->header('Cache-Control', 'public, max-age=300');\n\n// 2. Dispatch events after response with terminate middleware or defer():\ndefer(fn () => HeavyAnalyticsReporter::process(\$order));",
                    'docs_link' => 'https://laravel.com/docs/routing',
                ];
            }
        }

        return $recommendations;
    }

    /**
     * Diagnose an Edge between two architectural nodes.
     *
     * @param Edge $edge
     * @return array
     */
    public static function diagnoseEdge(Edge $edge): array
    {
        $recommendations = [];
        $avg = $edge->avgLatencyMs;

        if ($edge->status === 'critical' || $edge->status === 'warning') {
            $recommendations[] = [
                'severity' => $edge->status === 'critical' ? 'CRITICAL' : 'HIGH',
                'title' => "High Latency Connection [{$edge->source} ➔ {$edge->target}]",
                'category' => 'Network & Connection Latency',
                'symptom' => "Network operations over '{$edge->protocol}' average {$avg}ms per call.",
                'solution' => "Audit connection pool limits, verify keep-alive connection headers, check network VPC latency between application and '{$edge->target}'.",
                'code_snippet' => "// Enable connection reuse and persistent sockets for {$edge->protocol}",
                'docs_link' => 'https://laravel.com/docs',
            ];
        }

        return $recommendations;
    }
}
