<?php

namespace EmirKefi\TopologyMapper\Tests\Feature;

use EmirKefi\TopologyMapper\Facades\Topology;
use EmirKefi\TopologyMapper\Models\DataFlowPath;
use EmirKefi\TopologyMapper\Tests\TestCase;

class TraceFlowTest extends TestCase
{
    public function test_can_record_and_retrieve_end_to_end_flow_path(): void
    {
        $storage = Topology::getStorage();

        $flow = new DataFlowPath(
            traceId: 'trace-abc-123',
            originNodeId: 'controller:CheckoutController',
            originLabel: 'POST /checkout (CheckoutController@store)',
            originType: 'http_route',
            durationMs: 245.5,
            success: true
        );

        $flow->addHop('db:mysql:primary', 'sql', 'INSERT INTO orders', 12.4, true);
        $flow->addHop('queue:broker:redis', 'queue', 'Dispatch ProcessPaymentJob', 2.1, true);
        $flow->addHop('http:api.stripe.com', 'http', 'POST /v1/charges', 180.2, true);

        $storage->recordFlow($flow);

        $flows = $storage->getFlows();
        $this->assertCount(1, $flows);
        $this->assertEquals('trace-abc-123', $flows[0]->traceId);
        $this->assertCount(3, $flows[0]->hops);
        $this->assertEquals('db:mysql:primary', $flows[0]->hops[0]['target_node_id']);
        $this->assertEquals('queue:broker:redis', $flows[0]->hops[1]['target_node_id']);
        $this->assertEquals('http:api.stripe.com', $flows[0]->hops[2]['target_node_id']);
    }
}
