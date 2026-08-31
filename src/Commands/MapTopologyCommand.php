<?php

namespace EmirKefi\TopologyMapper\Commands;

use EmirKefi\TopologyMapper\Managers\TopologyManager;
use Illuminate\Console\Command;

class MapTopologyCommand extends Command
{
    protected $signature = 'topology:map 
                            {--zone= : Filter output by specific OSPF zone (zone_0, zone_1, zone_2, zone_3, zone_4)}
                            {--open : Explicitly open dashboard in browser}
                            {--no-open : Suppress automatically opening browser}';

    protected $description = 'Display network topology map in the console and automatically open the interactive visual simulation in your browser';

    public function handle(TopologyManager $manager): int
    {
        $graph = $manager->getEnrichedGraph();
        $appName = $graph['app_name'];
        $env = $graph['environment'];
        $healthScore = $graph['health']['score'];

        $this->newLine();
        $this->line("<fg=blue;options=bold>======================================================================</>");
        $this->line("<fg=cyan;options=bold>  🌐 LARAVEL APPLICATION NETWORK TOPOLOGY MAPPER</>");
        $this->line("  App: <fg=white;options=bold>{$appName}</> | Env: <fg=yellow>{$env}</> | Health Score: <fg=" . ($healthScore >= 90 ? 'green' : 'red') . ";options=bold>{$healthScore}%</>");
        $this->line("<fg=blue;options=bold>======================================================================</>");
        $this->newLine();

        $zoneFilter = $this->option('zone');
        $zones = $graph['zones'];

        foreach ($zones as $zoneKey => $zoneInfo) {
            if ($zoneFilter && $zoneFilter !== $zoneKey) {
                continue;
            }

            $nodesInZone = array_filter($graph['nodes'], fn ($n) => ($n['zone'] ?? '') === $zoneKey);
            $count = count($nodesInZone);

            $this->line("<fg=yellow;options=bold>▶ [{$zoneInfo['name']}]</> <fg=gray>({$count} Nodes) - {$zoneInfo['description']}</>");

            if (empty($nodesInZone)) {
                $this->line("    <fg=gray>└─ (No active nodes registered)</>");
            } else {
                $nodeRows = [];
                foreach ($nodesInZone as $node) {
                    $statusColor = match ($node['status']) {
                        'critical' => '<fg=red>● CRITICAL</>',
                        'warning' => '<fg=yellow>● WARNING</>',
                        default => '<fg=green>● HEALTHY</>',
                    };

                    $nodeRows[] = [
                        $node['id'],
                        $node['label'],
                        $node['driver'] ?? $node['type'],
                        $node['host'] ?? 'N/A',
                        $node['avg_latency_ms'] . 'ms',
                        $node['p95_latency_ms'] . 'ms',
                        $node['request_count'],
                        $statusColor,
                    ];
                }

                $this->table(
                    ['Node ID', 'Label', 'Driver/Type', 'Host/Endpoint', 'Avg Latency', 'P95', 'Requests', 'Status'],
                    $nodeRows
                );
            }
            $this->newLine();
        }

        // Bottlenecks & Anomaly Warning Section
        if (! empty($graph['bottlenecks'])) {
            $this->line("<fg=red;options=bold>⚠ DETECTED BOTTLENECK ANOMALIES & LATENCY HOTSPOTS:</>");
            foreach ($graph['bottlenecks'] as $b) {
                $this->line("  <fg=red>•</> <fg=white;options=bold>{$b['label']}</> <fg=yellow>({$b['avg_latency_ms']}ms)</> - <fg=gray>{$b['reason']}</>");
                if (! empty($b['recommendations'])) {
                    foreach ($b['recommendations'] as $rec) {
                        $severityColor = $rec['severity'] === 'CRITICAL' ? 'red' : ($rec['severity'] === 'HIGH' ? 'yellow' : 'cyan');
                        $this->line("    <fg={$severityColor};options=bold>🩺 Doctor Fix [{$rec['severity']}]:</> <fg=white>{$rec['title']}</>");
                        $this->line("       <fg=gray>↳ {$rec['solution']}</>");
                    }
                }
            }
            $this->newLine();
        }

        $url = url(config('topology.dashboard.path', 'topology'));
        $this->line("Dashboard Web Interface: <fg=cyan;options=bold>{$url}</>");
        $this->newLine();

        // Automatically open the URL unless --no-open is specified or running in unit tests
        if (! $this->option('no-open') && ! app()->runningUnitTests()) {
            $this->openInBrowser($url);
        }

        return self::SUCCESS;
    }

    /**
     * Cross-platform browser opening.
     */
    protected function openInBrowser(string $url): void
    {
        $this->info("🚀 Opening visual topology simulator in your default browser...");

        if (PHP_OS_FAMILY === 'Windows') {
            @pclose(@popen("start \"\" \"{$url}\"", 'r'));
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            @exec("open \"{$url}\" > /dev/null 2>&1 &");
        } else {
            @exec("xdg-open \"{$url}\" > /dev/null 2>&1 &");
        }
    }
}
