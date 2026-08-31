# Laravel Topology Mapper

<div align="center">

[![Latest Version on Packagist](https://img.shields.io/packagist/v/emirkefi/laravel-topology-mapper.svg?style=flat-square)](https://packagist.org/packages/emirkefi/laravel-topology-mapper)
[![Total Downloads](https://img.shields.io/packagist/dt/emirkefi/laravel-topology-mapper.svg?style=flat-square)](https://packagist.org/packages/emirkefi/laravel-topology-mapper)
[![License](https://img.shields.io/packagist/l/emirkefi/laravel-topology-mapper.svg?style=flat-square)](https://github.com/emirkefi/laravel-topology-mapper/blob/main/LICENSE.md)

**Dynamic visual network topology, telemetry, and live data-flow routing simulator for Laravel applications.**

</div>

---

##  Overview

Tools exist to map database schemas or list application routes, but until now there has been no tool that automatically maps a Laravel application's **living network topology**.

**Laravel Topology Mapper** inspects your `.env`, database connections (MySQL, PostgreSQL, Read Replicas), Redis clusters, message brokers (SQS, RabbitMQ, Redis Queue), and outbound HTTP client requests. It correlates end-to-end data flows and renders an **interactive, Cisco Packet Tracer-style visual simulation dashboard** with **OSPF-style architectural zones**, live packet animations, and real-time bottleneck detection.

---

##  Key Features

-  **Living Network Simulation**: Interactive physics-based canvas modeling your entire application infrastructure and external dependencies with zero front-end build step required.
-  **OSPF Architectural Zones**: Automatic classification of entities into architectural areas:
  - **Zone 0 (Backbone)**: Core Controllers, HTTP Routes, Kernel Entrypoints
  - **Area 1 (Data Tier)**: MySQL, PostgreSQL, SQLite, Read/Write Replicas
  - **Area 2 (Cache & In-Memory Tier)**: Redis Standalone & Clusters, Memcached
  - **Area 3 (Async & Queue Tier)**: SQS, RabbitMQ, Redis Queues, Background Job Workers
  - **Area 4 (External Autonomous Systems)**: Stripe, SendGrid, OpenAI, GitHub, S3, Webhooks
-  **Multi-Hop Trace Flow Correlator**: Replay the exact journey of data as it travels across process boundaries:
  `[POST /checkout] ➔ [DB: orders write] ➔ [Queue: ProcessOrderJob] ➔ [HTTP: api.stripe.com (142ms)] ➔ [Mail: Mailgun]`
-  **Bottleneck & Anomaly Detection**: Real-time identification of slow database queries, high-latency external APIs (>200ms warning, >1000ms critical), and failing dependencies.
-  **Static Scanner + Dynamic Telemetry**: Automatically scans your configurations on boot and enriches the graph dynamically with live traffic telemetry.
-  **Artisan CLI Map**: Print ANSI/ASCII network topology maps, health ratings, and latency metrics directly in your terminal.

---

##  Installation

Install the package via Composer:

```bash
composer require emirkefi/laravel-topology-mapper
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=topology-config
```

---

## ⚙️ Configuration

The published `config/topology.php` allows full customization:

```php
return [
    // Master switch
    'enabled' => env('TOPOLOGY_MAPPER_ENABLED', true),

    // Dashboard path and security
    'dashboard' => [
        'path' => env('TOPOLOGY_DASHBOARD_PATH', 'topology'),
        'middleware' => ['web'],
    ],

    // Storage driver: "cache", "file", or "memory"
    'storage' => [
        'driver' => env('TOPOLOGY_STORAGE_DRIVER', 'cache'),
        'cache_ttl' => 86400 * 7,
    ],

    // Latency anomaly thresholds (ms)
    'thresholds' => [
        'latency_warning_ms' => 200.0,
        'latency_critical_ms' => 1000.0,
    ],

    // Active telemetry interceptors
    'interceptors' => [
        'http' => true,
        'database' => true,
        'redis' => true,
        'queue' => true,
        'cache' => true,
        'mail' => true,
    ],
];
```

---

##  Dashboard & Web Interface

Navigate to `/topology` in your browser to access the visual dashboard:

- **Live Sync**: Automatically refreshes topology and animates live glowing packet pulses traversing active links.
- **Trace Flow Explorer**: Select any recorded HTTP request or queue job from the dropdown and watch the exact multi-hop route step through on the canvas.
- **Node Deep Dive**: Click any node to inspect latency percentiles (Avg, P95, Max), error rates, target endpoints, and caller metadata.
- **Exporting**: Export the network snapshot directly to JSON with a single click.

### Dashboard Security

By default, the dashboard is accessible in `local` and `testing` environments. In `production`, authorize access by defining the `viewTopology` gate in your `AuthServiceProvider` (similar to Laravel Horizon / Telescope):

```php
use App\Models\User;
use Illuminate\Support\Facades\Gate;

Gate::define('viewTopology', function (User $user) {
    return in_array($user->email, [
        'admin@yourdomain.com',
    ]);
});
```

---

##  Artisan CLI Commands

### 1. Visual ASCII Network Map & Browser Auto-Launch
Print an ASCII / ANSI visual map and latency overview in your terminal and automatically open the interactive simulation in your default browser:
```bash
php artisan topology:map
```
Filter by specific zone:
```bash
php artisan topology:map --zone=zone_4
```
Suppress browser auto-launch:
```bash
php artisan topology:map --no-open
```

### 2. Instant Browser Launcher
Instantly open the visual topology dashboard in your browser from the command line:
```bash
php artisan topology:open
```

### 3. Static Configuration Scan
Probe `.env` and configuration files to discover static infrastructure:
```bash
php artisan topology:scan
```

### 3. Clear Telemetry
Reset dynamic telemetry and flow history:
```bash
php artisan topology:clear
```

### 4. Export Topology Snapshot
Export full topology graph to a JSON file:
```bash
php artisan topology:export --path=storage/app/topology-backup.json
```

---

##  Testing

Run the test suite via PHPUnit:

```bash
vendor/bin/phpunit
```

---

##  Contributing

Contributions are welcome! Please check out [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## 📄 License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
