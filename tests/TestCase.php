<?php

namespace EmirKefi\TopologyMapper\Tests;

use EmirKefi\TopologyMapper\TopologyServiceProvider;
use Orchestra\Testbench\TestCase as OrchestraTestCase;

abstract class TestCase extends OrchestraTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            TopologyServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('topology.enabled', true);
        $app['config']->set('topology.storage.driver', 'memory');
        $app['config']->set('mail.default', 'smtp');
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }
}
