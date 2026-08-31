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

    public function test_performance_doctor_diagnoses_slow_database_and_external_apis(): void
    {
        $nodeDb = new \EmirKefi\TopologyMapper\Models\Node('db:slow', 'Slow MySQL', 'database', 'zone_1');
        $nodeDb->recordMetrics(450.0, true);

        $recsDb = \EmirKefi\TopologyMapper\Services\PerformanceDoctor::diagnoseNode($nodeDb, 200.0, 1000.0);
        $this->assertNotEmpty($recsDb);
        $this->assertEquals('Database Optimization', $recsDb[0]['category']);
        $this->assertStringContainsString('Eager load', $recsDb[0]['code_snippet']);

        $nodeApi = new \EmirKefi\TopologyMapper\Models\Node('http:api.stripe.com', 'Stripe API', 'external_api', 'zone_4', 'https://api.stripe.com');
        $nodeApi->recordMetrics(1200.0, false);

        $recsApi = \EmirKefi\TopologyMapper\Services\PerformanceDoctor::diagnoseNode($nodeApi, 200.0, 1000.0);
        $this->assertNotEmpty($recsApi);
        $this->assertEquals('CRITICAL', $recsApi[0]['severity']);
    }
}
