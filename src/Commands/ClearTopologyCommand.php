<?php

namespace EmirKefi\TopologyMapper\Commands;

use EmirKefi\TopologyMapper\Managers\TopologyManager;
use Illuminate\Console\Command;

class ClearTopologyCommand extends Command
{
    protected $signature = 'topology:clear';

    protected $description = 'Clear all recorded dynamic topology telemetry metrics and reset to static scan state';

    public function handle(TopologyManager $manager): int
    {
        $manager->clear();
        $this->info('Topology telemetry records and flow paths cleared successfully.');

        return self::SUCCESS;
    }
}
