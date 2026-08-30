<?php

namespace EmirKefi\TopologyMapper\Commands;

use Illuminate\Console\Command;

class OpenTopologyCommand extends Command
{
    protected $signature = 'topology:open';

    protected $description = 'Instantly launch the visual network topology simulator in your default web browser';

    public function handle(): int
    {
        $url = url(config('topology.dashboard.path', 'topology'));

        $this->newLine();
        $this->line("<fg=cyan;options=bold>🚀 Launching Laravel Topology Mapper...</>");
        $this->line("URL: <fg=white;options=bold>{$url}</>");
        $this->newLine();

        if (! app()->runningUnitTests()) {
            if (PHP_OS_FAMILY === 'Windows') {
                @pclose(@popen("start \"\" \"{$url}\"", 'r'));
            } elseif (PHP_OS_FAMILY === 'Darwin') {
                @exec("open \"{$url}\" > /dev/null 2>&1 &");
            } else {
                @exec("xdg-open \"{$url}\" > /dev/null 2>&1 &");
            }
        }

        return self::SUCCESS;
    }
}
