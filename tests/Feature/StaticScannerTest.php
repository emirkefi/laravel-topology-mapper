<?php

namespace EmirKefi\TopologyMapper\Tests\Feature;

use EmirKefi\TopologyMapper\Scanners\StaticConfigScanner;
use EmirKefi\TopologyMapper\Tests\TestCase;

class StaticScannerTest extends TestCase
{
    public function test_scans_app_core_and_database_nodes(): void
    {
        config()->set('database.connections.mysql_main', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'ecommerce_production',
        ]);

        config()->set('database.redis.cache_store', [
            'host' => 'redis.internal',
            'port' => 6379,
            'database' => 1,
        ]);

        config()->set('queue.connections.sqs_orders', [
            'driver' => 'sqs',
            'queue' => 'order-processing-queue',
        ]);

        config()->set('services.stripe', [
            'key' => 'secret_123',
            'domain' => 'api.stripe.com',
        ]);

        $scanner = new StaticConfigScanner();
        $results = $scanner->scan();

        $this->assertArrayHasKey('app:core', $results['nodes']);
        $this->assertEquals('zone_0', $results['nodes']['app:core']->zone);

        $this->assertArrayHasKey('db:mysql:mysql_main', $results['nodes']);
        $this->assertEquals('zone_1', $results['nodes']['db:mysql:mysql_main']->zone);

        $this->assertArrayHasKey('redis:cache_store', $results['nodes']);
        $this->assertEquals('zone_2', $results['nodes']['redis:cache_store']->zone);

        $this->assertArrayHasKey('queue:sqs:sqs_orders', $results['nodes']);
        $this->assertEquals('zone_3', $results['nodes']['queue:sqs:sqs_orders']->zone);

        $this->assertArrayHasKey('external:service:stripe', $results['nodes']);
        $this->assertEquals('zone_4', $results['nodes']['external:service:stripe']->zone);
    }
}
