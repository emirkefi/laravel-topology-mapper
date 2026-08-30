<?php

namespace EmirKefi\TopologyMapper\Commands;

use EmirKefi\TopologyMapper\Managers\TopologyManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ExportTopologyCommand extends Command
{
    protected $signature = 'topology:export {--path= : Target file path to write export JSON}';

    protected $description = 'Export network topology graph and telemetry statistics to a JSON snapshot file';

    public function handle(TopologyManager $manager): int
    {
        $targetPath = $this->option('path') ?: storage_path('app/topology/topology-export-' . date('Y-m-d-His') . '.json');

        $dir = dirname($targetPath);
        if (! File::isDirectory($dir)) {
            File::makeDirectory($dir, 0755, true, true);
        }

        $graph = $manager->getStorage()->exportGraph();
        File::put($targetPath, json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("Topology graph exported successfully to: {$targetPath}");

        return self::SUCCESS;
    }
}
