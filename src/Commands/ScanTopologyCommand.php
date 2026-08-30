<?php

namespace EmirKefi\TopologyMapper\Commands;

use EmirKefi\TopologyMapper\Managers\TopologyManager;
use Illuminate\Console\Command;

class ScanTopologyCommand extends Command
{
    protected $signature = 'topology:scan';

    protected $description = 'Scan Laravel application config files and environment to discover static topology nodes and connections';

    public function handle(TopologyManager $manager): int
    {
        $this->info('Scanning Laravel configuration (.env, databases, redis, queues, mail, services)...');

        $results = $manager->runStaticScan();
        $nodeCount = count($results['nodes']);
        $edgeCount = count($results['edges']);

        $this->info("Discovered {$nodeCount} static infrastructure nodes and {$edgeCount} primary dependency routes.");

        $rows = [];
        foreach ($results['nodes'] as $node) {
            $rows[] = [
                $node->id,
                $node->label,
                $node->zone,
                $node->type,
                $node->host ?? 'N/A',
            ];
        }

        $this->table(['Node ID', 'Label', 'Zone', 'Type', 'Target Host'], $rows);

        $this->info('Run [php artisan topology:map] to inspect full network map with live telemetry.');

        return self::SUCCESS;
    }
}
