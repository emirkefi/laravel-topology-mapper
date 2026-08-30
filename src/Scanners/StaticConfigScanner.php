<?php

namespace EmirKefi\TopologyMapper\Scanners;

use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;

class StaticConfigScanner
{
    /**
     * Scan Laravel configuration and return static topology nodes and initial static edges.
     *
     * @return array{nodes: array<string, Node>, edges: array<string, Edge>}
     */
    public function scan(): array
    {
        $nodes = [];
        $edges = [];

        // 1. Core Application Node (Zone 0 Backbone)
        $appName = config('topology.app_name', config('app.name', 'Laravel App'));
        $appNode = new Node(
            id: 'app:core',
            label: $appName . ' (Core)',
            type: 'app',
            zone: 'zone_0',
            host: config('app.url', 'localhost'),
            driver: 'laravel',
            status: 'healthy',
            metadata: [
                'laravel_version' => app()->version(),
                'php_version' => PHP_VERSION,
                'environment' => config('app.env', 'production'),
            ]
        );
        $nodes[$appNode->id] = $appNode;

        // 2. Database Connections (Zone 1 Data Tier)
        $dbConnections = config('database.connections', []);
        $defaultDb = config('database.default', 'mysql');

        foreach ($dbConnections as $connName => $connConfig) {
            $driver = $connConfig['driver'] ?? 'unknown';
            $host = $connConfig['host'] ?? ($connConfig['read']['host'][0] ?? ($connConfig['write']['host'][0] ?? 'localhost'));
            $database = $connConfig['database'] ?? '';
            $port = $connConfig['port'] ?? '';

            $dbNodeId = "db:{$driver}:{$connName}";
            $isDefault = ($connName === $defaultDb);

            $label = ucfirst($driver) . " DB ({$connName})";
            if ($database && ! str_contains($database, 'database.sqlite')) {
                $label .= " [{$database}]";
            }

            $dbNode = new Node(
                id: $dbNodeId,
                label: $label,
                type: 'database',
                zone: 'zone_1',
                host: $host . ($port ? ":{$port}" : ''),
                driver: $driver,
                metadata: [
                    'database_name' => $database,
                    'connection' => $connName,
                    'is_default' => $isDefault,
                    'has_read_write_separation' => isset($connConfig['read']) || isset($connConfig['write']),
                ]
            );
            $nodes[$dbNodeId] = $dbNode;

            $edge = new Edge(
                source: 'app:core',
                target: $dbNodeId,
                protocol: 'sql',
                operation: 'SQL Queries'
            );
            $edges[$edge->id] = $edge;
        }

        // 3. Redis Connections (Zone 2 In-Memory & Cache)
        $redisConnections = config('database.redis', []);
        $redisClient = $redisConnections['client'] ?? 'phpredis';

        foreach ($redisConnections as $connName => $connConfig) {
            if ($connName === 'client' || $connName === 'options') {
                continue;
            }

            $host = $connConfig['host'] ?? '127.0.0.1';
            $port = $connConfig['port'] ?? 6379;
            $dbIndex = $connConfig['database'] ?? 0;

            $redisNodeId = "redis:{$connName}";
            $redisNode = new Node(
                id: $redisNodeId,
                label: "Redis ({$connName}) [DB {$dbIndex}]",
                type: 'redis',
                zone: 'zone_2',
                host: "{$host}:{$port}",
                driver: "redis:{$redisClient}",
                metadata: [
                    'redis_client' => $redisClient,
                    'connection' => $connName,
                    'db_index' => $dbIndex,
                ]
            );
            $nodes[$redisNodeId] = $redisNode;

            $edge = new Edge(
                source: 'app:core',
                target: $redisNodeId,
                protocol: 'redis',
                operation: 'Redis Commands'
            );
            $edges[$edge->id] = $edge;
        }

        // 4. Queue Connections (Zone 3 Async & Queue Tier)
        $queueConnections = config('queue.connections', []);
        $defaultQueue = config('queue.default', 'sync');

        foreach ($queueConnections as $connName => $connConfig) {
            $driver = $connConfig['driver'] ?? 'sync';
            if ($driver === 'null' || $driver === 'sync') {
                continue;
            }

            $queueNodeId = "queue:{$driver}:{$connName}";
            $label = ucfirst($driver) . " Queue ({$connName})";
            $host = $connConfig['host'] ?? ($connConfig['queue'] ?? 'async');

            $queueNode = new Node(
                id: $queueNodeId,
                label: $label,
                type: 'queue',
                zone: 'zone_3',
                host: (string) $host,
                driver: $driver,
                metadata: [
                    'connection' => $connName,
                    'is_default' => ($connName === $defaultQueue),
                    'default_queue_name' => $connConfig['queue'] ?? 'default',
                ]
            );
            $nodes[$queueNodeId] = $queueNode;

            $edge = new Edge(
                source: 'app:core',
                target: $queueNodeId,
                protocol: 'queue',
                operation: 'Job Dispatch'
            );
            $edges[$edge->id] = $edge;
        }

        // 5. Mail Drivers (Zone 4 External / Messaging)
        $mailers = config('mail.mailers', []);
        $defaultMailer = config('mail.default', 'smtp');

        foreach ($mailers as $mailerName => $mailerConfig) {
            $transport = $mailerConfig['transport'] ?? 'smtp';
            if (in_array($transport, ['array', 'log'], true)) {
                continue;
            }

            $mailNodeId = "mail:{$transport}:{$mailerName}";
            $mailHost = $mailerConfig['host'] ?? ($mailerConfig['domain'] ?? $transport);

            $mailNode = new Node(
                id: $mailNodeId,
                label: ucfirst($transport) . " Mail ({$mailerName})",
                type: 'mail',
                zone: 'zone_4',
                host: (string) $mailHost,
                driver: $transport,
                metadata: [
                    'mailer' => $mailerName,
                    'is_default' => ($mailerName === $defaultMailer),
                ]
            );
            $nodes[$mailNodeId] = $mailNode;

            $edge = new Edge(
                source: 'app:core',
                target: $mailNodeId,
                protocol: 'smtp',
                operation: 'Send Email'
            );
            $edges[$edge->id] = $edge;
        }

        // 6. External Third-Party Services (config/services.php -> Zone 4 External Autonomous Systems)
        $services = config('services', []);
        foreach ($services as $serviceName => $serviceConfig) {
            if (! is_array($serviceConfig) || empty($serviceConfig)) {
                continue;
            }

            $serviceNodeId = "external:service:{$serviceName}";
            $label = ucfirst($serviceName) . " API";
            $domain = $serviceConfig['domain'] ?? ($serviceConfig['host'] ?? "{$serviceName}.api");

            $serviceNode = new Node(
                id: $serviceNodeId,
                label: $label,
                type: 'external_api',
                zone: 'zone_4',
                host: (string) $domain,
                driver: 'rest_api',
                metadata: [
                    'service_name' => $serviceName,
                ]
            );
            $nodes[$serviceNodeId] = $serviceNode;

            $edge = new Edge(
                source: 'app:core',
                target: $serviceNodeId,
                protocol: 'http',
                operation: 'HTTP Request'
            );
            $edges[$edge->id] = $edge;
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }
}
