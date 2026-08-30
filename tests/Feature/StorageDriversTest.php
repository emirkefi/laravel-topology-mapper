<?php

namespace EmirKefi\TopologyMapper\Tests\Feature;

use EmirKefi\TopologyMapper\Models\DataFlowPath;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;
use EmirKefi\TopologyMapper\Storage\CacheStorageDriver;
use EmirKefi\TopologyMapper\Storage\FileStorageDriver;
use EmirKefi\TopologyMapper\Tests\TestCase;
use Illuminate\Support\Facades\File;

class StorageDriversTest extends TestCase
{
    public function test_cache_storage_driver(): void
    {
        $driver = new CacheStorageDriver(['cache_key_prefix' => 'test_top:', 'cache_ttl' => 3600]);
        $driver->clear();

        $node = new Node('test:node:1', 'Test Node', 'app', 'zone_0');
        $node->recordMetrics(15.0, true);
        $driver->recordNode($node);

        $edge = new Edge('test:node:1', 'test:node:2', 'http', 'GET /api');
        $edge->recordMetrics(15.0, true, 'GET /api');
        $driver->recordEdge($edge);

        $flow = new DataFlowPath('tr-1', 'test:node:1', 'Flow 1', 'http_route', 15.0);
        $driver->recordFlow($flow);

        $nodes = $driver->getNodes();
        $this->assertArrayHasKey('test:node:1', $nodes);
        $this->assertEquals(1, $nodes['test:node:1']->requestCount);

        $edges = $driver->getEdges();
        $this->assertArrayHasKey('test:node:1->test:node:2', $edges);

        $flows = $driver->getFlows();
        $this->assertCount(1, $flows);

        $graph = $driver->exportGraph();
        $this->assertEquals(1, $graph['stats']['total_nodes']);

        $driver->clear();
        $this->assertEmpty($driver->getNodes());
    }

    public function test_file_storage_driver(): void
    {
        $filePath = storage_path('framework/testing/test-topology.json');
        if (File::exists($filePath)) {
            File::delete($filePath);
        }

        $driver = new FileStorageDriver(['file_path' => $filePath]);
        $driver->clear();

        $node = new Node('file:node:1', 'File Node', 'database', 'zone_1');
        $driver->recordNode($node);

        $this->assertArrayHasKey('file:node:1', $driver->getNodes());

        $driver->clear();
        $this->assertEmpty($driver->getNodes());
    }
}
