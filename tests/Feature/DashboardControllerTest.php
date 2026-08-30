<?php

namespace EmirKefi\TopologyMapper\Tests\Feature;

use EmirKefi\TopologyMapper\Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    public function test_dashboard_index_renders_successfully(): void
    {
        $response = $this->get('/topology');

        $response->assertStatus(200);
        $response->assertSee('Laravel Topology Mapper');
        $response->assertSee('OSPF Architectural Zones');
    }

    public function test_api_graph_returns_valid_json_payload(): void
    {
        $response = $this->getJson('/topology/api/graph');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'app_name',
            'environment',
            'zones',
            'nodes',
            'edges',
            'flows',
            'health' => [
                'score',
                'status',
                'healthy_nodes',
            ],
            'bottlenecks',
            'summary',
        ]);
    }

    public function test_api_scan_and_clear_endpoints(): void
    {
        $scanResponse = $this->postJson('/topology/api/scan');
        $scanResponse->assertStatus(200);
        $scanResponse->assertJson(['message' => 'Topology scan completed successfully.']);

        $clearResponse = $this->postJson('/topology/api/clear');
        $clearResponse->assertStatus(200);
        $clearResponse->assertJson(['message' => 'Topology telemetry metrics cleared.']);
    }
}
