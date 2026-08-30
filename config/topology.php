<?php

return [
    'enabled' => env('TOPOLOGY_MAPPER_ENABLED', true),

    'storage' => [
        'driver' => env('TOPOLOGY_STORAGE_DRIVER', 'cache'), // 'cache', 'database', or 'file'
        'cache_key' => 'laravel_topology_graph',
        'cache_ttl' => 86400, // 24 hours
    ],

    'interceptors' => [
        'http' => true,
        'database' => true,
        'redis' => true,
    ],

    'ignore' => [
        'hosts' => [
            '127.0.0.1',
            'localhost',
        ],
    ],
];