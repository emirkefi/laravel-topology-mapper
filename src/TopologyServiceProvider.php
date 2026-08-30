<?php

namespace EmirKefi\TopologyMapper;

use EmirKefi\TopologyMapper\Commands\ClearTopologyCommand;
use EmirKefi\TopologyMapper\Commands\ExportTopologyCommand;
use EmirKefi\TopologyMapper\Commands\MapTopologyCommand;
use EmirKefi\TopologyMapper\Commands\OpenTopologyCommand;
use EmirKefi\TopologyMapper\Commands\ScanTopologyCommand;
use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Http\Controllers\TopologyDashboardController;
use EmirKefi\TopologyMapper\Http\Middleware\AuthorizeTopology;
use EmirKefi\TopologyMapper\Http\Middleware\TraceRequestMiddleware;
use EmirKefi\TopologyMapper\Managers\TopologyManager;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class TopologyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/topology.php', 'topology');

        $this->app->singleton(TopologyManager::class, function ($app) {
            return new TopologyManager(config('topology', []));
        });

        $this->app->singleton(StorageDriverInterface::class, function ($app) {
            return $app->make(TopologyManager::class)->getStorage();
        });
    }

    public function boot(): void
    {
        // 1. Publishing configuration and views
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/topology.php' => config_path('topology.php'),
            ], 'topology-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/topology-mapper'),
            ], 'topology-views');

            // Register artisan commands
            $this->commands([
                MapTopologyCommand::class,
                OpenTopologyCommand::class,
                ScanTopologyCommand::class,
                ClearTopologyCommand::class,
                ExportTopologyCommand::class,
            ]);
        }

        // 2. Load Views
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'topology-mapper');

        if (! config('topology.enabled', true)) {
            return;
        }

        // 3. Register Dashboard Web Routes
        $this->registerRoutes();

        // 4. Register Global HTTP Request Tracing Middleware
        $this->registerMiddleware();

        // 5. Initialize dynamic interceptors and static scan
        $this->app->make(TopologyManager::class)->bootInterceptors();
    }

    protected function registerRoutes(): void
    {
        $dashboardConfig = config('topology.dashboard', []);
        $path = $dashboardConfig['path'] ?? 'topology';
        $domain = $dashboardConfig['domain'] ?? null;
        $middleware = $dashboardConfig['middleware'] ?? ['web'];

        Route::group([
            'prefix' => $path,
            'domain' => $domain,
            'middleware' => array_merge($middleware, [AuthorizeTopology::class]),
            'as' => 'topology.',
        ], function () {
            Route::get('/', [TopologyDashboardController::class, 'index'])->name('dashboard');
            Route::get('/api/graph', [TopologyDashboardController::class, 'graph'])->name('api.graph');
            Route::get('/api/flows', [TopologyDashboardController::class, 'flows'])->name('api.flows');
            Route::post('/api/clear', [TopologyDashboardController::class, 'clear'])->name('api.clear');
            Route::post('/api/scan', [TopologyDashboardController::class, 'scan'])->name('api.scan');
            Route::get('/api/export', [TopologyDashboardController::class, 'export'])->name('api.export');
        });
    }

    protected function registerMiddleware(): void
    {
        if ($this->app->bound(Kernel::class)) {
            /** @var \Illuminate\Foundation\Http\Kernel $kernel */
            $kernel = $this->app->make(Kernel::class);
            if (method_exists($kernel, 'pushMiddleware')) {
                $kernel->pushMiddleware(TraceRequestMiddleware::class);
            }
        }
    }
}