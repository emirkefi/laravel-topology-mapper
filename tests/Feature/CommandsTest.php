<?php

namespace EmirKefi\TopologyMapper\Tests\Feature;

use EmirKefi\TopologyMapper\Tests\TestCase;

class CommandsTest extends TestCase
{
    public function test_artisan_topology_map_command(): void
    {
        $this->artisan('topology:map')
            ->assertExitCode(0)
            ->expectsOutputToContain('LARAVEL APPLICATION NETWORK TOPOLOGY MAPPER');
    }

    public function test_artisan_topology_scan_command(): void
    {
        $this->artisan('topology:scan')
            ->assertExitCode(0)
            ->expectsOutputToContain('Scanning Laravel configuration');
    }

    public function test_artisan_topology_clear_command(): void
    {
        $this->artisan('topology:clear')
            ->assertExitCode(0)
            ->expectsOutputToContain('Topology telemetry records and flow paths cleared successfully.');
    }

    public function test_artisan_topology_open_command(): void
    {
        $this->artisan('topology:open')
            ->assertExitCode(0)
            ->expectsOutputToContain('Launching Laravel Topology Mapper');
    }
}
