<?php

namespace EmirKefi\TopologyMapper\Tests\Feature;

use EmirKefi\TopologyMapper\Facades\Topology;
use EmirKefi\TopologyMapper\Listeners\HttpClientListener;
use EmirKefi\TopologyMapper\Support\TraceContext;
use EmirKefi\TopologyMapper\Tests\TestCase;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Events\ResponseReceived;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\DB;

class TelemetryListenersTest extends TestCase
{
    public function test_database_queries_update_topology_graph(): void
    {
        DB::statement('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
        DB::insert('INSERT INTO users (name) VALUES (?)', ['John Doe']);
        $users = DB::select('SELECT * FROM users');

        $this->assertCount(1, $users);

        $graph = Topology::getEnrichedGraph();
        $dbNodes = collect($graph['nodes'])->where('type', 'database')->values();

        $this->assertNotEmpty($dbNodes);
        $totalRequests = $dbNodes->sum('request_count');
        $this->assertGreaterThanOrEqual(1, $totalRequests);
    }

    public function test_http_client_response_records_external_node_and_edge(): void
    {
        $storage = Topology::getStorage();
        $listener = new HttpClientListener($storage);

        TraceContext::start('controller:OrderController', 'POST /orders');

        $psrRequest = new Psr7Request('POST', 'https://api.stripe.com/v1/charges');
        $clientRequest = new ClientRequest($psrRequest);
        $psrResponse = new Psr7Response(200, [], json_encode(['id' => 'ch_123', 'paid' => true]));
        $clientResponse = new ClientResponse($psrResponse);

        $listener->handleRequestSending(new \Illuminate\Http\Client\Events\RequestSending($clientRequest));
        $listener->handleResponseReceived(new ResponseReceived($clientRequest, $clientResponse));

        $nodes = $storage->getNodes();
        $this->assertArrayHasKey('http:api.stripe.com', $nodes);
        $stripeNode = $nodes['http:api.stripe.com'];
        $this->assertEquals('zone_4', $stripeNode->zone);
        $this->assertEquals('external_api', $stripeNode->type);

        $edges = $storage->getEdges();
        $this->assertArrayHasKey('controller:OrderController->http:api.stripe.com', $edges);

        TraceContext::reset();
    }
}
