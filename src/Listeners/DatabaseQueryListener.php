<?php

namespace EmirKefi\TopologyMapper\Listeners;

use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;
use EmirKefi\TopologyMapper\Support\TraceContext;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;

class DatabaseQueryListener
{
    protected static bool $handling = false;

    public function __construct(protected StorageDriverInterface $storage)
    {
    }

    public function handle(QueryExecuted $event): void
    {
        if (self::$handling) {
            return;
        }

        self::$handling = true;

        try {
            $connName = $event->connectionName ?: ($event->connection->getName() ?: 'default');
            $driver = $event->connection->getDriverName();
            $dbNodeId = "db:{$driver}:{$connName}";

            $durationMs = (float) $event->time; // Laravel reports query time in ms
            $sql = trim($event->sql);
            $firstWord = strtoupper(strtok($sql, " \n\t"));
            $operation = in_array($firstWord, ['SELECT', 'INSERT', 'UPDATE', 'DELETE', 'SHOW', 'PRAGMA', 'CREATE', 'ALTER', 'DROP'])
                ? "{$firstWord} Query"
                : 'SQL Query';

            // 1. Record Database Node (Zone 1)
            $node = new Node(
                id: $dbNodeId,
                label: ucfirst($driver) . " DB ({$connName})",
                type: 'database',
                zone: 'zone_1',
                host: $event->connection->getConfig('host') ?? 'localhost',
                driver: $driver,
                metadata: [
                    'connection' => $connName,
                    'database' => $event->connection->getDatabaseName(),
                ]
            );
            $node->recordMetrics($durationMs, true);
            $this->storage->recordNode($node);

            // 2. Record Edge
            $sourceId = TraceContext::getOriginNodeId() ?? 'app:core';
            $edge = new Edge(
                source: $sourceId,
                target: $dbNodeId,
                protocol: 'sql',
                operation: $operation,
                metadata: [
                    'last_sql_snippet' => Str::limit($sql, 100),
                ]
            );
            $edge->recordMetrics($durationMs, true, $operation);
            $this->storage->recordEdge($edge);

            // 3. Add Hop to Active Trace
            TraceContext::addHop($dbNodeId, 'sql', $operation, $durationMs, true, [
                'snippet' => Str::limit($sql, 80),
            ]);
        } finally {
            self::$handling = false;
        }
    }
}
