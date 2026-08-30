<?php

namespace YourVendor\TopologyMapper;

use Illuminate\Support\ServiceProvider;
use YourVendor\TopologyMapper\Managers\TopologyManager;

class TopologyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/topology.php', 'topology');

        $this->app->singleton(TopologyManager::class, function ($app) {
            return new TopologyManager(config('topology'));
        });
    }

    public function boot(): void
    {
        if (! config('topology.enabled')) {
            return;
        }

        if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
            $this->publishes([
                __DIR__ . '/../config/topology.php' => config_path('topology.php'),
            ], 'topology-config');
        }

        $this->app->make(TopologyManager::class)->bootInterceptors();
    }
}