<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Topology Mapper Master Switch
    |--------------------------------------------------------------------------
    |
    | Enable or disable dynamic network topology mapping in your application.
    | When disabled, all listeners and middlewares will act as no-ops.
    |
    */
    'enabled' => env('TOPOLOGY_MAPPER_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Application Identification
    |--------------------------------------------------------------------------
    |
    | The name and environment identifier for this node in multi-service or
    | distributed architectures.
    |
    */
    'app_name' => env('APP_NAME', 'Laravel App'),
    'environment' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Dashboard Routing & Security
    |--------------------------------------------------------------------------
    |
    | Configure the URL path, middleware protection, and optional domain for
    | accessing the interactive visual network topology dashboard.
    |
    */
    'dashboard' => [
        'path' => env('TOPOLOGY_DASHBOARD_PATH', 'topology'),
        'domain' => env('TOPOLOGY_DASHBOARD_DOMAIN', null),
        'middleware' => ['web'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "cache", "file", "array".
    | - cache: Stores nodes, edges, and sliding metrics in Laravel's cache (Redis, Memcached, etc.)
    | - file: Stores snapshots and state in storage/app/topology/
    |
    */
    'storage' => [
        'driver' => env('TOPOLOGY_STORAGE_DRIVER', 'cache'),
        'cache_key_prefix' => 'topology_mapper:',
        'cache_ttl' => env('TOPOLOGY_CACHE_TTL', 86400 * 7), // 7 days rolling retention
        'file_path' => storage_path('app/topology/topology-graph.json'),
        'max_recorded_flows' => env('TOPOLOGY_MAX_FLOWS', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telemetry Interceptors
    |--------------------------------------------------------------------------
    |
    | Toggle individual runtime interceptors to collect dynamic dependency calls.
    |
    */
    'interceptors' => [
        'http' => env('TOPOLOGY_INTERCEPT_HTTP', true),
        'database' => env('TOPOLOGY_INTERCEPT_DATABASE', true),
        'redis' => env('TOPOLOGY_INTERCEPT_REDIS', true),
        'queue' => env('TOPOLOGY_INTERCEPT_QUEUE', true),
        'cache' => env('TOPOLOGY_INTERCEPT_CACHE', true),
        'mail' => env('TOPOLOGY_INTERCEPT_MAIL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling Rate
    |--------------------------------------------------------------------------
    |
    | Configure sampling percentage (1.0 = 100%, 0.1 = 10%) for high-traffic apps.
    |
    */
    'sample_rate' => (float) env('TOPOLOGY_SAMPLE_RATE', 1.0),

    /*
    |--------------------------------------------------------------------------
    | Bottleneck & Latency Thresholds (Milliseconds)
    |--------------------------------------------------------------------------
    |
    | Nodes and edges exceeding these latency thresholds will be visually
    | highlighted with OSPF-style warning (amber) and critical (red) indicators.
    |
    */
    'thresholds' => [
        'latency_warning_ms' => (float) env('TOPOLOGY_LATENCY_WARN_MS', 200.0),
        'latency_critical_ms' => (float) env('TOPOLOGY_LATENCY_CRIT_MS', 1000.0),
        'error_rate_warning' => 0.05, // 5% error rate triggers warning
        'error_rate_critical' => 0.20, // 20% error rate triggers critical
    ],

    /*
    |--------------------------------------------------------------------------
    | OSPF-Style Architectural Zones
    |--------------------------------------------------------------------------
    |
    | Defines the conceptual zones for categorizing your application nodes.
    |
    */
    'zones' => [
        'zone_0' => [
            'id' => 'zone_0',
            'name' => 'Backbone (Core App)',
            'description' => 'Controllers, Middleware, HTTP Routing, and Kernel Entrypoints',
            'color' => '#3b82f6', // Electric Blue
        ],
        'zone_1' => [
            'id' => 'zone_1',
            'name' => 'Area 1: Data Tier',
            'description' => 'Relational Databases (MySQL, PostgreSQL), Read Replicas, and Primary DBs',
            'color' => '#10b981', // Emerald Green
        ],
        'zone_2' => [
            'id' => 'zone_2',
            'name' => 'Area 2: Cache & In-Memory',
            'description' => 'Redis Clusters, Memcached, and In-Memory Stores',
            'color' => '#ef4444', // Ruby Red
        ],
        'zone_3' => [
            'id' => 'zone_3',
            'name' => 'Area 3: Async & Queue Tier',
            'description' => 'Message Brokers (SQS, RabbitMQ, Redis Queue) and Background Job Workers',
            'color' => '#f59e0b', // Amber / Gold
        ],
        'zone_4' => [
            'id' => 'zone_4',
            'name' => 'Area 4: External Autonomous Systems',
            'description' => 'Third-Party APIs, Payment Gateways (Stripe), Email (Mailgun/SES), AI/LLMs',
            'color' => '#8b5cf6', // Violet / Purple
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Ignored Hosts and Patterns
    |--------------------------------------------------------------------------
    |
    | Traffic to these domains or hosts will not be recorded in dynamic topology.
    |
    */
    'ignore' => [
        'hosts' => [
            '127.0.0.1',
            'localhost',
            '::1',
        ],
        'ignore_local_storage' => false,
        'sanitize_headers' => [
            'authorization',
            'x-api-key',
            'cookie',
            'set-cookie',
        ],
    ],
];