<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laravel Topology Mapper - Network Simulation & Dependency Telemetry</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-base: #06090e;
            --bg-surface: #0b111e;
            --bg-card: #111a2e;
            --bg-card-hover: #17233d;
            --border: #1e2c47;
            --border-highlight: #3b82f6;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --text-dim: #64748b;
            --color-zone0: #3b82f6; /* Blue Backbone */
            --color-zone1: #10b981; /* Green Data */
            --color-zone2: #ef4444; /* Red Cache */
            --color-zone3: #f59e0b; /* Amber Queue */
            --color-zone4: #a855f7; /* Purple External */
            --color-healthy: #10b981;
            --color-warning: #f59e0b;
            --color-critical: #ef4444;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: var(--bg-base);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            overflow: hidden;
            height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
            user-select: none;
        }

        code, pre, .mono {
            font-family: 'JetBrains Mono', monospace;
        }

        /* Header Navbar */
        header {
            height: 60px;
            background: rgba(11, 17, 30, 0.92);
            backdrop-filter: blur(16px);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            z-index: 50;
        }

        .brand-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo-badge {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #2563eb, #8b5cf6);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 18px;
            color: #fff;
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.45);
        }

        .app-meta h1 {
            font-size: 15px;
            font-weight: 700;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .app-meta .badge-env {
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            padding: 2px 6px;
            border-radius: 4px;
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .system-stats {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .stat-label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-dim);
            font-weight: 600;
        }

        .stat-value {
            font-size: 13px;
            font-weight: 700;
            color: var(--text-main);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.15s ease;
        }

        .btn:hover {
            background: var(--bg-card-hover);
            border-color: var(--border-highlight);
            box-shadow: 0 0 12px rgba(59, 130, 246, 0.25);
        }

        .btn-primary {
            background: #2563eb;
            border-color: #3b82f6;
            color: #ffffff;
        }

        .btn-primary:hover {
            background: #1d4ed8;
            border-color: #60a5fa;
        }

        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.25);
            border-color: #ef4444;
        }

        /* Layout Container */
        .workspace {
            display: flex;
            flex: 1;
            position: relative;
            overflow: hidden;
        }

        /* Canvas Arena */
        #canvas-container {
            flex: 1;
            position: relative;
            background: radial-gradient(circle at center, #0c1222 0%, #04060a 100%);
            overflow: hidden;
        }

        canvas {
            display: block;
            width: 100%;
            height: 100%;
            cursor: grab;
        }

        canvas:active {
            cursor: grabbing;
        }

        /* Overlay Control Hub */
        .control-hub {
            position: absolute;
            top: 16px;
            left: 16px;
            background: rgba(11, 17, 30, 0.94);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            z-index: 10;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.6);
            width: 250px;
        }

        .control-hub h3 {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-dim);
            font-weight: 700;
        }

        .zone-legend {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .zone-pill {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 600;
            padding: 5px 8px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.15s;
        }

        .zone-pill:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .zone-pill.active {
            background: rgba(255, 255, 255, 0.06);
            border-color: currentColor;
        }

        .zone-pill.muted {
            opacity: 0.3;
        }

        .zone-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
            display: inline-block;
        }

        /* Floating Canvas Navigation Pad */
        .zoom-controls {
            position: absolute;
            bottom: 84px;
            right: 16px;
            display: flex;
            flex-direction: column;
            gap: 6px;
            z-index: 10;
        }

        .zoom-btn {
            width: 36px;
            height: 36px;
            background: rgba(11, 17, 30, 0.9);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            cursor: pointer;
            backdrop-filter: blur(8px);
            transition: all 0.15s;
        }

        .zoom-btn:hover {
            background: var(--bg-card-hover);
            border-color: var(--border-highlight);
        }

        /* Trace Flow Player Overlay */
        .trace-player {
            position: absolute;
            bottom: 16px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(11, 17, 30, 0.96);
            backdrop-filter: blur(16px);
            border: 1px solid var(--border-highlight);
            border-radius: 12px;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            z-index: 20;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.7), 0 0 20px rgba(59, 130, 246, 0.2);
            max-width: 900px;
            width: calc(100% - 64px);
        }

        .trace-selector-box {
            flex: 1;
        }

        .trace-selector-box select {
            width: 100%;
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-family: inherit;
            cursor: pointer;
            outline: none;
        }

        .trace-selector-box select:focus {
            border-color: var(--border-highlight);
        }

        .flow-stepper {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Sidebar Panels */
        .sidebar-panel {
            width: 380px;
            background: var(--bg-surface);
            border-left: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 15;
        }

        .sidebar-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sidebar-header h2 {
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sidebar-content {
            flex: 1;
            overflow-y: auto;
            padding: 16px;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 14px;
        }

        .card-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-muted);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .metric-box {
            background: rgba(0, 0, 0, 0.25);
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.03);
        }

        .metric-box .label {
            font-size: 10px;
            color: var(--text-dim);
        }

        .metric-box .val {
            font-size: 13px;
            font-weight: 700;
            margin-top: 2px;
        }

        /* Bottlenecks List */
        .bottleneck-item {
            display: flex;
            flex-direction: column;
            gap: 4px;
            padding: 10px;
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.25);
            border-radius: 6px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.15s;
        }

        .bottleneck-item:hover {
            background: rgba(239, 68, 68, 0.18);
            border-color: #ef4444;
            transform: translateX(2px);
        }

        .bottleneck-item .b-header {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: 700;
            color: #fca5a5;
        }

        .bottleneck-item .b-reason {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* Doctor Recommendation Box */
        .doctor-fix-box {
            margin-top: 8px;
            padding: 8px;
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.25);
            border-radius: 6px;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .doctor-fix-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 11px;
            font-weight: 700;
            color: #34d399;
        }

        .doctor-badge-sev {
            font-size: 9px;
            font-weight: 800;
            text-transform: uppercase;
            padding: 1px 5px;
            border-radius: 4px;
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }

        .doctor-badge-sev.HIGH {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            border-color: rgba(245, 158, 11, 0.4);
        }

        .doctor-badge-sev.MEDIUM {
            background: rgba(59, 130, 246, 0.2);
            color: #60a5fa;
            border-color: rgba(59, 130, 246, 0.4);
        }

        .doctor-fix-title {
            font-size: 11px;
            font-weight: 600;
            color: #e2e8f0;
        }

        .doctor-fix-solution {
            font-size: 11px;
            color: #94a3b8;
            line-height: 1.4;
        }

        .doctor-code-block {
            background: #040711;
            border: 1px solid #1e293b;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 10px;
            color: #38bdf8;
            white-space: pre-wrap;
            word-break: break-all;
            position: relative;
            max-height: 140px;
            overflow-y: auto;
        }

        /* Node Details Modal / Drawer */
        .drawer-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 100;
            display: none;
            justify-content: flex-end;
        }

        .drawer {
            width: 440px;
            background: var(--bg-surface);
            height: 100%;
            border-left: 1px solid var(--border);
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 20px;
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.5);
            animation: slideIn 0.2s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        .pulse-circle {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 6px;
            animation: pulseDot 1.5s infinite;
        }

        @keyframes pulseDot {
            0% { transform: scale(0.9); opacity: 0.7; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(0.9); opacity: 0.7; }
        }
    </style>
</head>
<body>

    <!-- Header Navigation -->
    <header>
        <div class="brand-section">
            <div class="logo-badge">☊</div>
            <div class="app-meta">
                <h1>{{ $initialGraph['app_name'] }} <span class="badge-env">{{ $initialGraph['environment'] }}</span></h1>
                <div style="font-size: 11px; color: var(--text-dim);">Living Application Network Topology & OSPF Route Simulator</div>
            </div>
        </div>

        <div class="system-stats">
            <div class="stat-item">
                <span class="stat-label">Health Score</span>
                <span class="stat-value" id="hdr-health-score" style="color: var(--color-healthy);">
                    {{ $initialGraph['health']['score'] }}%
                </span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Active Nodes</span>
                <span class="stat-value" id="hdr-nodes-count">{{ $initialGraph['summary']['total_nodes'] }}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Live Edges</span>
                <span class="stat-value" id="hdr-edges-count">{{ $initialGraph['summary']['total_edges'] }}</span>
            </div>
            <div class="stat-item">
                <span class="stat-label">Recorded Flows</span>
                <span class="stat-value" id="hdr-flows-count">{{ $initialGraph['summary']['total_flows'] }}</span>
            </div>
        </div>

        <div class="header-actions">
            <button class="btn" id="btn-scan" title="Scan static app configs and connections">
                <span>⚡</span> Scan Config
            </button>
            <button class="btn" id="btn-export-json" title="Export Topology JSON">
                <span>⬇</span> Export JSON
            </button>
            <button class="btn btn-danger" id="btn-clear" title="Reset dynamic metrics">
                <span>↺</span> Clear Metrics
            </button>
            <button class="btn btn-primary" id="btn-toggle-poll">
                <span class="pulse-circle" style="background: #22c55e;"></span>
                <span id="poll-label">Live Sync</span>
            </button>
        </div>
    </header>

    <!-- Main Workspace -->
    <div class="workspace">

        <!-- Canvas Arena -->
        <div id="canvas-container">
            <canvas id="topologyCanvas"></canvas>

            <!-- OSPF Zone Selector Hub -->
            <div class="control-hub">
                <h3>OSPF Architectural Zones</h3>
                <div class="zone-legend">
                    <div class="zone-pill active" data-zone="zone_0" style="color: var(--color-zone0);">
                        <span><span class="zone-indicator" style="background: var(--color-zone0);"></span>Zone 0: Backbone</span>
                        <span class="mono" id="zcount-0">0</span>
                    </div>
                    <div class="zone-pill active" data-zone="zone_1" style="color: var(--color-zone1);">
                        <span><span class="zone-indicator" style="background: var(--color-zone1);"></span>Area 1: Data Tier</span>
                        <span class="mono" id="zcount-1">0</span>
                    </div>
                    <div class="zone-pill active" data-zone="zone_2" style="color: var(--color-zone2);">
                        <span><span class="zone-indicator" style="background: var(--color-zone2);"></span>Area 2: Cache Tier</span>
                        <span class="mono" id="zcount-2">0</span>
                    </div>
                    <div class="zone-pill active" data-zone="zone_3" style="color: var(--color-zone3);">
                        <span><span class="zone-indicator" style="background: var(--color-zone3);"></span>Area 3: Async Queue</span>
                        <span class="mono" id="zcount-3">0</span>
                    </div>
                    <div class="zone-pill active" data-zone="zone_4" style="color: var(--color-zone4);">
                        <span><span class="zone-indicator" style="background: var(--color-zone4);"></span>Area 4: External AS</span>
                        <span class="mono" id="zcount-4">0</span>
                    </div>
                </div>

                <div style="margin-top: 6px; border-top: 1px solid var(--border); padding-top: 8px; display: flex; gap: 6px;">
                    <button class="btn" style="flex: 1; padding: 5px 6px; font-size: 10px;" id="btn-center">Fit View</button>
                    <button class="btn" style="flex: 1; padding: 5px 6px; font-size: 10px;" id="btn-layout">Reorganize</button>
                </div>
            </div>

            <!-- Floating Navigation Controls -->
            <div class="zoom-controls">
                <button class="zoom-btn" id="btn-zoom-in" title="Zoom In">+</button>
                <button class="zoom-btn" id="btn-zoom-out" title="Zoom Out">−</button>
                <button class="zoom-btn" id="btn-zoom-reset" title="Reset View">⊙</button>
            </div>

            <!-- Trace Flow Player -->
            <div class="trace-player">
                <div style="font-size: 11px; font-weight: 700; color: #60a5fa; text-transform: uppercase; display: flex; align-items: center; gap: 6px;">
                    <span>⚡</span> Flow Trace:
                </div>
                <div class="trace-selector-box">
                    <select id="flow-select">
                        <option value="">-- Select a Recorded Request / Job Flow Path --</option>
                    </select>
                </div>
                <div class="flow-stepper">
                    <button class="btn btn-primary" id="btn-replay-flow" style="font-weight: 700; letter-spacing: 0.02em;">
                        ▶ Play Packet Flow
                    </button>
                    <span id="flow-status" class="mono" style="font-size: 11px; color: var(--text-muted); min-width: 140px;">Ready</span>
                </div>
            </div>
        </div>

        <!-- Sidebar Panel: Insights & Bottlenecks -->
        <div class="sidebar-panel">
            <div class="sidebar-header">
                <h2><span>🔍</span> Network Telemetry</h2>
                <span class="badge-env" id="sidebar-grade">Grade A</span>
            </div>

            <div class="sidebar-content">
                <!-- Bottlenecks & Anomalies -->
                <div class="card">
                    <div class="card-title">
                        <span>Bottlenecks & Latency Warnings</span>
                        <span class="badge-env" id="bottleneck-count" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; border-color: #ef4444;">0</span>
                    </div>
                    <div id="bottlenecks-list">
                        <div style="font-size: 12px; color: var(--text-dim); text-align: center; padding: 10px;">
                            No performance bottlenecks detected. System is running within optimal latency thresholds.
                        </div>
                    </div>
                </div>

                <!-- Latency Summary -->
                <div class="card">
                    <div class="card-title">Latency Overview</div>
                    <div class="metric-grid">
                        <div class="metric-box">
                            <div class="label">Slowest External API</div>
                            <div class="val mono" id="stat-slow-api">--</div>
                        </div>
                        <div class="metric-box">
                            <div class="label">Slowest DB Query</div>
                            <div class="val mono" id="stat-slow-db">--</div>
                        </div>
                        <div class="metric-box">
                            <div class="label">P95 System Latency</div>
                            <div class="val mono" id="stat-p95">--</div>
                        </div>
                        <div class="metric-box">
                            <div class="label">Avg Network Delay</div>
                            <div class="val mono" id="stat-avg-delay">--</div>
                        </div>
                    </div>
                </div>

                <!-- Instructions Tip -->
                <div class="card" style="background: rgba(59, 130, 246, 0.05); border-color: rgba(59, 130, 246, 0.2);">
                    <div class="card-title" style="color: #60a5fa;">Simulation Navigation</div>
                    <div style="font-size: 11px; color: var(--text-muted); line-height: 1.6;">
                        • <strong>Click any node</strong> to view connection telemetry & latency percentiles.<br>
                        • <strong>Drag nodes</strong> to rearrange cluster layouts.<br>
                        • <strong>Scroll wheel</strong> to zoom, drag background to pan.<br>
                        • Select any recorded flow below and click <strong>Play Packet Flow</strong> to replay data routing.
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Node Detail Drawer -->
    <div class="drawer-overlay" id="node-drawer-overlay">
        <div class="drawer" id="node-drawer">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <h2 id="drawer-node-label" style="font-size: 17px; font-weight: 700; color: #fff;">Node Name</h2>
                    <div class="mono" id="drawer-node-id" style="font-size: 11px; color: var(--text-dim); margin-top: 2px;">id:sample</div>
                </div>
                <button class="btn" id="btn-close-drawer" style="padding: 4px 8px;">✕</button>
            </div>

            <div class="card">
                <div class="card-title">Node Telemetry</div>
                <div class="metric-grid">
                    <div class="metric-box">
                        <div class="label">OSPF Zone</div>
                        <div class="val" id="drawer-zone">Zone 0</div>
                    </div>
                    <div class="metric-box">
                        <div class="label">Health Status</div>
                        <div class="val" id="drawer-status">Healthy</div>
                    </div>
                    <div class="metric-box">
                        <div class="label">Avg Latency</div>
                        <div class="val mono" id="drawer-avg-latency">0ms</div>
                    </div>
                    <div class="metric-box">
                        <div class="label">P95 Latency</div>
                        <div class="val mono" id="drawer-p95-latency">0ms</div>
                    </div>
                    <div class="metric-box">
                        <div class="label">Total Invocations</div>
                        <div class="val mono" id="drawer-requests">0</div>
                    </div>
                    <div class="metric-box">
                        <div class="label">Error Rate</div>
                        <div class="val mono" id="drawer-error-rate">0%</div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">Host & Connection Details</div>
                <div style="display: flex; flex-direction: column; gap: 8px; font-size: 12px;">
                    <div><span style="color: var(--text-dim);">Host/Endpoint:</span> <span class="mono" id="drawer-host">--</span></div>
                    <div><span style="color: var(--text-dim);">Driver/Protocol:</span> <span class="mono" id="drawer-driver">--</span></div>
                    <div><span style="color: var(--text-dim);">Last Seen:</span> <span class="mono" id="drawer-last-seen">--</span></div>
                </div>
            </div>

            <div class="card" id="drawer-doctor-card" style="border-color: rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.04);">
                <div class="card-title" style="color: #34d399;">
                    <span>🩺 Doctor Recommendations</span>
                </div>
                <div id="drawer-doctor-content" style="display: flex; flex-direction: column; gap: 8px;">
                    <div style="font-size: 11px; color: var(--text-dim);">No performance issues detected for this component.</div>
                </div>
            </div>

            <div class="card">
                <div class="card-title">Metadata & Attributes</div>
                <pre class="mono" id="drawer-metadata" style="font-size: 11px; background: rgba(0,0,0,0.3); padding: 8px; border-radius: 4px; overflow-x: auto; color: #38bdf8;">{}</pre>
            </div>
        </div>
    </div>

    <!-- Self-Contained Spacious Collision Physics & Simulation Engine -->
    <script>
        let graphData = @json($initialGraph);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        const canvas = document.getElementById('topologyCanvas');
        const ctx = canvas.getContext('2d');
        let width = canvas.clientWidth;
        let height = canvas.clientHeight;

        let panX = 0, panY = 0;
        let zoom = 0.55;
        let isDragging = false;
        let dragNode = null;
        let lastMouseX = 0, lastMouseY = 0;

        let nodes = [];
        let links = [];
        let particles = [];
        let selectedNode = null;
        let activeZoneFilter = null;

        // Flow Trace Playback State
        let activeFlowOriginNode = null;
        let activeFlowHops = [];
        let flowAnimationIndex = -1;
        let flowAnimationTimer = null;
        let flowPulseProgress = 0;

        let isPolling = true;
        let pollTimer = null;

        const ZONE_COLORS = {
            'zone_0': '#3b82f6', // Blue Backbone
            'zone_1': '#10b981', // Green Data
            'zone_2': '#ef4444', // Red Cache
            'zone_3': '#f59e0b', // Amber Queue
            'zone_4': '#a855f7', // Purple External
        };

        // Generous spatial anchor coordinates for OSPF zones
        const ZONE_CENTERS = {
            'zone_0': { x: 0, y: 0, label: 'Backbone (Zone 0)' },
            'zone_1': { x: -950, y: -520, label: 'Data Tier (Area 1)' },
            'zone_2': { x: 950, y: -520, label: 'Cache Tier (Area 2)' },
            'zone_3': { x: -950, y: 520, label: 'Queue Tier (Area 3)' },
            'zone_4': { x: 950, y: 520, label: 'External AS (Area 4)' },
        };

        function resizeCanvas() {
            width = canvas.parentElement.clientWidth;
            height = canvas.parentElement.clientHeight;
            canvas.width = width * window.devicePixelRatio;
            canvas.height = height * window.devicePixelRatio;
            ctx.scale(window.devicePixelRatio, window.devicePixelRatio);
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        function computeTargetPositions() {
            const zones = ['zone_0', 'zone_1', 'zone_2', 'zone_3', 'zone_4'];
            zones.forEach(zoneKey => {
                const zoneNodes = nodes.filter(n => n.zone === zoneKey);
                const center = ZONE_CENTERS[zoneKey] || { x: 0, y: 0 };

                if (zoneKey === 'zone_0') {
                    const core = zoneNodes.find(n => n.id === 'app:core');
                    if (core) {
                        core.targetX = center.x;
                        core.targetY = center.y;
                    }
                    const others = zoneNodes.filter(n => n.id !== 'app:core');
                    others.forEach((n, idx) => {
                        const ring = Math.floor(idx / 8);
                        const pos = idx % 8;
                        const ringTotal = Math.min(8, others.length - ring * 8);
                        const radius = 220 + ring * 140;
                        const angle = (pos / Math.max(1, ringTotal)) * Math.PI * 2 + (ring * 0.4);
                        n.targetX = center.x + Math.cos(angle) * radius;
                        n.targetY = center.y + Math.sin(angle) * radius;
                    });
                } else {
                    zoneNodes.forEach((n, idx) => {
                        const ring = Math.floor(idx / 6);
                        const pos = idx % 6;
                        const ringTotal = Math.min(6, zoneNodes.length - ring * 6);
                        const radius = (ring === 0 && zoneNodes.length === 1) ? 0 : (160 + ring * 140);
                        const angle = (pos / Math.max(1, ringTotal)) * Math.PI * 2 + (ring * 0.5);
                        n.targetX = center.x + Math.cos(angle) * radius;
                        n.targetY = center.y + Math.sin(angle) * radius;
                    });
                }
            });
        }

        function buildGraph(data) {
            graphData = data;
            const existingPos = new Map();
            nodes.forEach(n => existingPos.set(n.id, { x: n.x, y: n.y, vx: n.vx, vy: n.vy }));

            nodes = (data.nodes || []).map((raw, idx) => {
                const zone = raw.zone || 'zone_0';
                const center = ZONE_CENTERS[zone] || { x: 0, y: 0 };
                const prev = existingPos.get(raw.id);

                return {
                    ...raw,
                    x: prev ? prev.x : center.x + (Math.random() - 0.5) * 100,
                    y: prev ? prev.y : center.y + (Math.random() - 0.5) * 100,
                    vx: prev ? prev.vx : 0,
                    vy: prev ? prev.vy : 0,
                    radius: raw.id === 'app:core' ? 36 : (raw.type === 'database' || raw.type === 'external_api' ? 26 : 22),
                    color: ZONE_COLORS[zone] || '#3b82f6',
                };
            });

            computeTargetPositions();

            // Set initial positions directly to target if new
            nodes.forEach(n => {
                if (!existingPos.has(n.id)) {
                    n.x = n.targetX || 0;
                    n.y = n.targetY || 0;
                }
            });

            links = (data.edges || []).map(edge => {
                return {
                    ...edge,
                    sourceNode: nodes.find(n => n.id === edge.source),
                    targetNode: nodes.find(n => n.id === edge.target),
                };
            }).filter(link => link.sourceNode && link.targetNode);

            updateUI();
            populateFlowSelector();
        }

        // Multi-Pass Rigid Collision & Smooth Target Attractor
        function simulatePhysics() {
            // 1. Strict multi-pass collision solver with 170px minimum spacing buffer
            for (let iter = 0; iter < 6; iter++) {
                for (let i = 0; i < nodes.length; i++) {
                    for (let j = i + 1; j < nodes.length; j++) {
                        const n1 = nodes[i];
                        const n2 = nodes[j];
                        const dx = n2.x - n1.x;
                        const dy = n2.y - n1.y;
                        const dist = Math.sqrt(dx * dx + dy * dy) || 0.0001;
                        const minDist = 170; // 170px minimum distance guarantees zero circle or text overlap!

                        if (dist < minDist) {
                            const push = (minDist - dist) * 0.5;
                            const nx = dx / dist;
                            const ny = dy / dist;
                            if (n1 !== dragNode) { n1.x -= nx * push; n1.y -= ny * push; }
                            if (n2 !== dragNode) { n2.x += nx * push; n2.y += ny * push; }
                        }
                    }
                }
            }

            // 2. Smooth spring pull toward orbital anchor positions
            nodes.forEach(node => {
                if (node === dragNode) return;
                const tx = node.targetX ?? 0;
                const ty = node.targetY ?? 0;
                const dx = tx - node.x;
                const dy = ty - node.y;

                node.vx = (node.vx || 0) * 0.72 + dx * 0.035;
                node.vy = (node.vy || 0) * 0.72 + dy * 0.035;

                node.x += node.vx;
                node.y += node.vy;
            });

            // 3. Live traffic packet pulses
            if (Math.random() < 0.35 && links.length > 0) {
                const randomLink = links[Math.floor(Math.random() * links.length)];
                particles.push({
                    link: randomLink,
                    progress: 0,
                    speed: 0.018 + Math.random() * 0.02,
                    color: randomLink.status === 'critical' ? '#ef4444' : (randomLink.status === 'warning' ? '#f59e0b' : '#38bdf8'),
                    size: 3.5 + Math.random() * 2
                });
            }

            particles.forEach(p => p.progress += p.speed);
            particles = particles.filter(p => p.progress < 1.0);
        }

        // Render Canvas Frame
        function render() {
            simulatePhysics();

            ctx.save();
            ctx.clearRect(0, 0, width, height);

            ctx.translate(width / 2 + panX, height / 2 + panY);
            ctx.scale(zoom, zoom);

            // Draw Dynamic OSPF Zone Boundaries
            drawZoneBoundaries();

            // Draw Directed Edges
            links.forEach(link => {
                drawLink(link);
            });

            // Draw Active Flow Trace Highlight
            if (activeFlowHops.length > 0) {
                drawActiveFlow();
            }

            // Draw Animated Traffic Pulses
            particles.forEach(p => {
                const s = p.link.sourceNode;
                const t = p.link.targetNode;
                const px = s.x + (t.x - s.x) * p.progress;
                const py = s.y + (t.y - s.y) * p.progress;

                ctx.save();
                ctx.beginPath();
                ctx.arc(px, py, p.size, 0, Math.PI * 2);
                ctx.fillStyle = p.color;
                ctx.shadowColor = p.color;
                ctx.shadowBlur = 12;
                ctx.fill();
                ctx.restore();
            });

            // Draw Nodes
            nodes.forEach(node => {
                drawNode(node);
            });

            ctx.restore();
            requestAnimationFrame(render);
        }

        // Dynamic Zone Pods that dynamically wrap their members with generous padding
        function drawZoneBoundaries() {
            const zones = ['zone_0', 'zone_1', 'zone_2', 'zone_3', 'zone_4'];

            zones.forEach(zoneKey => {
                const isFiltered = activeZoneFilter && activeZoneFilter !== zoneKey;
                const color = ZONE_COLORS[zoneKey] || '#3b82f6';
                const center = ZONE_CENTERS[zoneKey];
                const zoneNodes = nodes.filter(n => n.zone === zoneKey);

                let podRadius = 260;
                let podCenterX = center.x;
                let podCenterY = center.y;

                if (zoneNodes.length > 0) {
                    let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
                    zoneNodes.forEach(n => {
                        minX = Math.min(minX, n.x);
                        maxX = Math.max(maxX, n.x);
                        minY = Math.min(minY, n.y);
                        maxY = Math.max(maxY, n.y);
                    });

                    podCenterX = (minX + maxX) / 2;
                    podCenterY = (minY + maxY) / 2;

                    let maxDist = 0;
                    zoneNodes.forEach(n => {
                        const d = Math.sqrt((n.x - podCenterX) ** 2 + (n.y - podCenterY) ** 2);
                        maxDist = Math.max(maxDist, d);
                    });
                    podRadius = Math.max(220, maxDist + 110);
                }

                ctx.save();
                ctx.beginPath();
                ctx.arc(podCenterX, podCenterY, podRadius, 0, Math.PI * 2);
                ctx.fillStyle = isFiltered ? 'rgba(10, 15, 25, 0.15)' : color + '08';
                ctx.strokeStyle = isFiltered ? 'rgba(50, 60, 80, 0.12)' : color + '30';
                ctx.lineWidth = isFiltered ? 1 : 1.5;
                ctx.setLineDash([8, 8]);
                ctx.fill();
                ctx.stroke();

                // Zone Title Badge
                ctx.save();
                ctx.font = '700 12px "Plus Jakarta Sans", sans-serif';
                const text = center.label.toUpperCase();
                const textWidth = ctx.measureText(text).width;

                ctx.fillStyle = isFiltered ? 'rgba(20, 28, 45, 0.4)' : '#0b111e';
                ctx.strokeStyle = isFiltered ? 'rgba(50, 60, 80, 0.2)' : color + '60';
                ctx.lineWidth = 1;
                ctx.setLineDash([]);
                ctx.beginPath();
                ctx.roundRect(podCenterX - textWidth / 2 - 12, podCenterY - podRadius - 14, textWidth + 24, 26, 6);
                ctx.fill();
                ctx.stroke();

                ctx.fillStyle = isFiltered ? '#475569' : color;
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(text, podCenterX, podCenterY - podRadius - 1);
                ctx.restore();

                ctx.restore();
            });
        }

        function drawLink(link) {
            const s = link.sourceNode;
            const t = link.targetNode;

            const isDimmed = activeZoneFilter && (s.zone !== activeZoneFilter && t.zone !== activeZoneFilter);

            ctx.save();
            ctx.beginPath();
            ctx.moveTo(s.x, s.y);
            ctx.lineTo(t.x, t.y);

            let strokeColor = isDimmed ? 'rgba(30, 40, 60, 0.12)' : 'rgba(75, 85, 110, 0.45)';
            let lineWidth = 1.5;

            if (!isDimmed) {
                if (link.status === 'critical') {
                    strokeColor = '#ef4444';
                    lineWidth = 2.5;
                } else if (link.status === 'warning') {
                    strokeColor = '#f59e0b';
                    lineWidth = 2.0;
                } else if (link.protocol === 'http') {
                    strokeColor = 'rgba(168, 85, 247, 0.65)';
                } else if (link.protocol === 'sql') {
                    strokeColor = 'rgba(16, 185, 129, 0.65)';
                } else if (link.protocol === 'redis') {
                    strokeColor = 'rgba(239, 68, 68, 0.65)';
                }
            }

            ctx.strokeStyle = strokeColor;
            ctx.lineWidth = lineWidth;
            ctx.stroke();

            // Draw latency pill along edge when slow or inspected
            if (!isDimmed && (link.avgLatencyMs >= 150 || (selectedNode && (selectedNode.id === s.id || selectedNode.id === t.id)))) {
                const midX = (s.x + t.x) / 2;
                const midY = (s.y + t.y) / 2;

                ctx.save();
                const latencyText = `${link.avgLatencyMs}ms`;
                ctx.font = '600 10px "JetBrains Mono", monospace';
                const pillWidth = ctx.measureText(latencyText).width + 10;

                ctx.fillStyle = '#0b111e';
                ctx.strokeStyle = link.avgLatencyMs >= 1000 ? '#ef4444' : (link.avgLatencyMs >= 200 ? '#f59e0b' : '#334155');
                ctx.lineWidth = 1;
                ctx.beginPath();
                ctx.roundRect(midX - pillWidth / 2, midY - 9, pillWidth, 18, 4);
                ctx.fill();
                ctx.stroke();

                ctx.fillStyle = link.avgLatencyMs >= 1000 ? '#fca5a5' : (link.avgLatencyMs >= 200 ? '#fcd34d' : '#94a3b8');
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText(latencyText, midX, midY);
                ctx.restore();
            }

            ctx.restore();
        }

        function drawActiveFlow() {
            activeFlowHops.forEach((hop, idx) => {
                const targetNode = nodes.find(n => n.id === hop.target_node_id);
                if (!targetNode) return;

                let originNode = null;
                if (idx === 0) {
                    originNode = activeFlowOriginNode || nodes.find(n => n.id === 'app:core');
                } else {
                    originNode = nodes.find(n => n.id === activeFlowHops[idx - 1].target_node_id) || activeFlowOriginNode || nodes.find(n => n.id === 'app:core');
                }

                if (!originNode || originNode.id === targetNode.id) return;

                const isCurrentHop = (idx === flowAnimationIndex);

                ctx.save();
                ctx.beginPath();
                ctx.moveTo(originNode.x, originNode.y);
                ctx.lineTo(targetNode.x, targetNode.y);
                ctx.strokeStyle = isCurrentHop ? '#38bdf8' : 'rgba(56, 189, 248, 0.7)';
                ctx.lineWidth = isCurrentHop ? 5 : 2.5;
                ctx.shadowColor = '#38bdf8';
                ctx.shadowBlur = isCurrentHop ? 20 : 8;
                ctx.stroke();

                if (isCurrentHop) {
                    const px = originNode.x + (targetNode.x - originNode.x) * flowPulseProgress;
                    const py = originNode.y + (targetNode.y - originNode.y) * flowPulseProgress;

                    ctx.beginPath();
                    ctx.arc(px, py, 8, 0, Math.PI * 2);
                    ctx.fillStyle = '#ffffff';
                    ctx.shadowColor = '#38bdf8';
                    ctx.shadowBlur = 24;
                    ctx.fill();
                }

                ctx.restore();
            });
        }

        function drawNode(node) {
            const isSelected = selectedNode && selectedNode.id === node.id;
            const isDimmed = activeZoneFilter && node.zone !== activeZoneFilter;

            ctx.save();
            if (isDimmed) {
                ctx.globalAlpha = 0.22;
            }

            // Glow / Shadow
            ctx.beginPath();
            ctx.arc(node.x, node.y, node.radius, 0, Math.PI * 2);

            const glowColor = node.status === 'critical' ? '#ef4444' : (node.status === 'warning' ? '#f59e0b' : node.color);
            ctx.shadowColor = glowColor;
            ctx.shadowBlur = isSelected ? 26 : (node.status !== 'healthy' ? 18 : 10);

            // Node Body Gradient
            ctx.fillStyle = node.status === 'critical' ? '#7f1d1d' : (node.status === 'warning' ? '#78350f' : '#0d1527');
            ctx.fill();

            // Node Border
            ctx.lineWidth = isSelected ? 3.5 : (node.status !== 'healthy' ? 2.5 : 2);
            ctx.strokeStyle = node.status === 'critical' ? '#ef4444' : (node.status === 'warning' ? '#f59e0b' : (isSelected ? '#ffffff' : node.color));
            ctx.stroke();

            // Symbol Inside Node
            ctx.font = '700 13px "JetBrains Mono", monospace';
            ctx.fillStyle = '#ffffff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            let symbol = '●';
            if (node.type === 'database') symbol = '⛁';
            else if (node.type === 'redis') symbol = '◆';
            else if (node.type === 'queue') symbol = '⇆';
            else if (node.type === 'external_api') symbol = '☁';
            else if (node.type === 'mail') symbol = '✉';
            else if (node.type === 'app') symbol = '⚡';
            ctx.fillText(symbol, node.x, node.y);

            // Label Container Box Behind Text
            ctx.shadowBlur = 0;
            ctx.font = '600 11px "Plus Jakarta Sans", sans-serif';
            const labelText = node.label.length > 26 ? node.label.substring(0, 24) + '…' : node.label;
            const textWidth = ctx.measureText(labelText).width;

            ctx.fillStyle = 'rgba(11, 17, 30, 0.92)';
            ctx.strokeStyle = isSelected ? '#38bdf8' : 'rgba(51, 65, 85, 0.55)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.roundRect(node.x - textWidth / 2 - 8, node.y + node.radius + 6, textWidth + 16, 20, 4);
            ctx.fill();
            ctx.stroke();

            // Label Text
            ctx.fillStyle = isSelected ? '#ffffff' : '#f8fafc';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(labelText, node.x, node.y + node.radius + 16);

            // Latency Tag
            if (node.avgLatencyMs > 0) {
                const latText = `${node.avgLatencyMs}ms`;
                ctx.font = '600 10px "JetBrains Mono", monospace';
                ctx.fillStyle = node.avgLatencyMs >= 1000 ? '#ef4444' : (node.avgLatencyMs >= 200 ? '#f59e0b' : '#64748b');
                ctx.fillText(latText, node.x, node.y + node.radius + 36);
            }

            ctx.restore();
        }

        // Interactions: Pan, Zoom, Drag
        canvas.addEventListener('mousedown', (e) => {
            const rect = canvas.getBoundingClientRect();
            const mouseX = (e.clientX - rect.left - width / 2 - panX) / zoom;
            const mouseY = (e.clientY - rect.top - height / 2 - panY) / zoom;

            const clicked = nodes.find(n => {
                const dx = n.x - mouseX;
                const dy = n.y - mouseY;
                return Math.sqrt(dx * dx + dy * dy) <= n.radius + 8;
            });

            if (clicked) {
                dragNode = clicked;
                selectedNode = clicked;
                openNodeDrawer(clicked);
            } else {
                isDragging = true;
                lastMouseX = e.clientX;
                lastMouseY = e.clientY;
            }
        });

        window.addEventListener('mousemove', (e) => {
            if (dragNode) {
                const rect = canvas.getBoundingClientRect();
                dragNode.x = (e.clientX - rect.left - width / 2 - panX) / zoom;
                dragNode.y = (e.clientY - rect.top - height / 2 - panY) / zoom;
                dragNode.targetX = dragNode.x;
                dragNode.targetY = dragNode.y;
                dragNode.vx = 0;
                dragNode.vy = 0;
            } else if (isDragging) {
                panX += (e.clientX - lastMouseX);
                panY += (e.clientY - lastMouseY);
                lastMouseX = e.clientX;
                lastMouseY = e.clientY;
            }
        });

        window.addEventListener('mouseup', () => {
            dragNode = null;
            isDragging = false;
        });

        canvas.addEventListener('wheel', (e) => {
            e.preventDefault();
            const zoomFactor = e.deltaY < 0 ? 1.1 : 0.9;
            zoom = Math.max(0.15, Math.min(3.5, zoom * zoomFactor));
        });

        // Fit View function to center and frame all nodes
        function fitView() {
            if (nodes.length === 0) return;

            let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
            nodes.forEach(n => {
                minX = Math.min(minX, n.x - 120);
                maxX = Math.max(maxX, n.x + 120);
                minY = Math.min(minY, n.y - 120);
                maxY = Math.max(maxY, n.y + 120);
            });

            const graphWidth = maxX - minX || 1200;
            const graphHeight = maxY - minY || 800;

            const scaleX = (width - 180) / graphWidth;
            const scaleY = (height - 180) / graphHeight;
            zoom = Math.max(0.25, Math.min(1.0, Math.min(scaleX, scaleY)));

            panX = -((minX + maxX) / 2) * zoom;
            panY = -((minY + maxY) / 2) * zoom;
        }

        // UI Updates & Flow Selector
        function updateUI() {
            document.getElementById('hdr-health-score').textContent = `${graphData.health?.score || 100}%`;
            document.getElementById('hdr-nodes-count').textContent = graphData.summary?.total_nodes || nodes.length;
            document.getElementById('hdr-edges-count').textContent = graphData.summary?.total_edges || links.length;
            document.getElementById('hdr-flows-count').textContent = graphData.summary?.total_flows || (graphData.flows || []).length;

            ['zone_0', 'zone_1', 'zone_2', 'zone_3', 'zone_4'].forEach((z, i) => {
                const count = nodes.filter(n => n.zone === z).length;
                const el = document.getElementById(`zcount-${i}`);
                if (el) el.textContent = count;
            });

            const bList = document.getElementById('bottlenecks-list');
            const bottlenecks = graphData.bottlenecks || [];
            document.getElementById('bottleneck-count').textContent = bottlenecks.length;

            if (bottlenecks.length === 0) {
                bList.innerHTML = `<div style="font-size: 12px; color: var(--text-dim); text-align: center; padding: 10px;">
                    No performance bottlenecks detected. System is running within optimal latency thresholds.
                </div>`;
            } else {
                bList.innerHTML = bottlenecks.map(b => {
                    const recs = b.recommendations || [];
                    const recsHtml = recs.length > 0 ? recs.map(r => `
                        <div class="doctor-fix-box">
                            <div class="doctor-fix-header">
                                <span>🩺 ${r.category}</span>
                                <span class="doctor-badge-sev ${r.severity}">${r.severity}</span>
                            </div>
                            <div class="doctor-fix-title">${r.title}</div>
                            <div class="doctor-fix-solution">💡 ${r.solution}</div>
                            ${r.code_snippet ? `<pre class="doctor-code-block">${r.code_snippet}</pre>` : ''}
                        </div>
                    `).join('') : '';

                    return `
                        <div class="bottleneck-item" onclick="focusNode('${b.id}')">
                            <div class="b-header">
                                <span>${b.label}</span>
                                <span class="mono">${b.avg_latency_ms}ms</span>
                            </div>
                            <div class="b-reason">${b.reason}</div>
                            ${recsHtml}
                        </div>
                    `;
                }).join('');
            }

            let maxApiLatency = 0, slowApi = '--';
            let maxDbLatency = 0, slowDb = '--';
            let allLatencies = [];

            nodes.forEach(n => {
                if (n.latencies) allLatencies.push(...n.latencies);
                if (n.type === 'external_api' && n.avgLatencyMs > maxApiLatency) {
                    maxApiLatency = n.avgLatencyMs;
                    slowApi = `${n.avgLatencyMs}ms (${n.label.split(' ')[0]})`;
                }
                if (n.type === 'database' && n.avgLatencyMs > maxDbLatency) {
                    maxDbLatency = n.avgLatencyMs;
                    slowDb = `${n.avgLatencyMs}ms (${n.driver})`;
                }
            });

            document.getElementById('stat-slow-api').textContent = slowApi;
            document.getElementById('stat-slow-db').textContent = slowDb;

            if (allLatencies.length > 0) {
                allLatencies.sort((a, b) => a - b);
                const p95 = allLatencies[Math.floor(allLatencies.length * 0.95)] || 0;
                const avg = (allLatencies.reduce((a, b) => a + b, 0) / allLatencies.length).toFixed(1);
                document.getElementById('stat-p95').textContent = `${p95}ms`;
                document.getElementById('stat-avg-delay').textContent = `${avg}ms`;
            }
        }

        function populateFlowSelector() {
            const select = document.getElementById('flow-select');
            const flows = graphData.flows || [];
            const previousValue = select.value;

            select.innerHTML = '<option value="">-- Select a Recorded Request / Job Flow Path --</option>' +
                flows.map((f, idx) => `
                    <option value="${idx}">[${f.duration_ms}ms] ${f.origin_label} ➔ ${f.hop_count || (f.hops ? f.hops.length : 1)} hops</option>
                `).join('');

            if (previousValue && flows[previousValue]) {
                select.value = previousValue;
            }
        }

        // Interactive Flow Trace Replayer
        document.getElementById('btn-replay-flow').addEventListener('click', () => {
            const select = document.getElementById('flow-select');
            let flowIdx = select.value;

            const flows = graphData.flows || [];
            if (flows.length === 0) {
                document.getElementById('flow-status').textContent = 'No recorded flows yet';
                return;
            }

            if (flowIdx === '') {
                flowIdx = 0;
                select.value = 0;
            }

            const flow = flows[flowIdx];
            if (!flow) return;

            activeFlowOriginNode = nodes.find(n => n.id === flow.origin_node_id) || nodes.find(n => n.id === 'app:core');
            activeFlowHops = flow.hops && flow.hops.length > 0 ? flow.hops : [
                { target_node_id: flow.origin_node_id, protocol: 'http', operation: flow.origin_label, duration_ms: flow.duration_ms }
            ];

            flowAnimationIndex = 0;
            flowPulseProgress = 0;

            const targetFirst = nodes.find(n => n.id === activeFlowHops[0].target_node_id) || activeFlowOriginNode;
            if (targetFirst) {
                panX = -targetFirst.x * zoom;
                panY = -targetFirst.y * zoom;
            }

            clearInterval(flowAnimationTimer);

            const hopInterval = Math.max(600, Math.min(1200, 3000 / activeFlowHops.length));
            let stepStart = Date.now();

            flowAnimationTimer = setInterval(() => {
                const now = Date.now();
                flowPulseProgress = Math.min(1.0, (now - stepStart) / hopInterval);

                const currentHop = activeFlowHops[flowAnimationIndex];
                if (currentHop) {
                    const targetHopNode = nodes.find(n => n.id === currentHop.target_node_id);
                    document.getElementById('flow-status').textContent = `Hop ${flowAnimationIndex + 1}/${activeFlowHops.length}: ${currentHop.operation || (targetHopNode ? targetHopNode.label : '')} (${currentHop.duration_ms || 0}ms)`;
                }

                if (flowPulseProgress >= 1.0) {
                    flowAnimationIndex++;
                    stepStart = Date.now();
                    flowPulseProgress = 0;

                    if (flowAnimationIndex >= activeFlowHops.length) {
                        clearInterval(flowAnimationTimer);
                        document.getElementById('flow-status').textContent = `✓ Completed in ${flow.duration_ms}ms`;
                        setTimeout(() => { activeFlowHops = []; }, 4000);
                    } else {
                        const nextTarget = nodes.find(n => n.id === activeFlowHops[flowAnimationIndex].target_node_id);
                        if (nextTarget) {
                            panX = -nextTarget.x * zoom;
                            panY = -nextTarget.y * zoom;
                        }
                    }
                }
            }, 30);
        });

        // Zone Pill Filtering
        document.querySelectorAll('.zone-pill').forEach(pill => {
            pill.addEventListener('click', () => {
                const zone = pill.getAttribute('data-zone');
                if (activeZoneFilter === zone) {
                    activeZoneFilter = null;
                    document.querySelectorAll('.zone-pill').forEach(p => p.classList.remove('muted', 'active'));
                    document.querySelectorAll('.zone-pill').forEach(p => p.classList.add('active'));
                } else {
                    activeZoneFilter = zone;
                    document.querySelectorAll('.zone-pill').forEach(p => {
                        if (p.getAttribute('data-zone') === zone) {
                            p.classList.remove('muted');
                            p.classList.add('active');
                        } else {
                            p.classList.remove('active');
                            p.classList.add('muted');
                        }
                    });

                    const center = ZONE_CENTERS[zone];
                    if (center) {
                        panX = -center.x * zoom;
                        panY = -center.y * zoom;
                    }
                }
            });
        });

        // Zoom & Fit Controls
        document.getElementById('btn-center').addEventListener('click', fitView);
        document.getElementById('btn-zoom-reset').addEventListener('click', fitView);

        document.getElementById('btn-zoom-in').addEventListener('click', () => {
            zoom = Math.min(3.5, zoom * 1.25);
        });

        document.getElementById('btn-zoom-out').addEventListener('click', () => {
            zoom = Math.max(0.15, zoom * 0.8);
        });

        document.getElementById('btn-layout').addEventListener('click', () => {
            computeTargetPositions();
            nodes.forEach(n => {
                n.x = n.targetX || 0;
                n.y = n.targetY || 0;
                n.vx = 0;
                n.vy = 0;
            });
            fitView();
        });

        // Drawer details
        function openNodeDrawer(node) {
            document.getElementById('drawer-node-label').textContent = node.label;
            document.getElementById('drawer-node-id').textContent = `ID: ${node.id}`;
            document.getElementById('drawer-zone').textContent = node.zone.replace('_', ' ').toUpperCase();
            document.getElementById('drawer-status').textContent = node.status.toUpperCase();
            document.getElementById('drawer-status').style.color = node.status === 'critical' ? '#ef4444' : (node.status === 'warning' ? '#f59e0b' : '#10b981');
            document.getElementById('drawer-avg-latency').textContent = `${node.avgLatencyMs || 0}ms`;
            document.getElementById('drawer-p95-latency').textContent = `${node.p95_latency_ms || 0}ms`;
            document.getElementById('drawer-requests').textContent = node.requestCount || 0;
            document.getElementById('drawer-error-rate').textContent = `${((node.error_rate || 0) * 100).toFixed(1)}%`;
            document.getElementById('drawer-host').textContent = node.host || 'N/A';
            document.getElementById('drawer-driver').textContent = node.driver || 'N/A';
            document.getElementById('drawer-last-seen').textContent = node.lastSeenAt || 'Now';
            document.getElementById('drawer-metadata').textContent = JSON.stringify(node.metadata || {}, null, 2);

            // Populate Doctor Recommendations for node
            const bottlenecks = graphData.bottlenecks || [];
            const nodeBottleneck = bottlenecks.find(b => b.id === node.id);
            const doctorContainer = document.getElementById('drawer-doctor-content');

            if (nodeBottleneck && nodeBottleneck.recommendations && nodeBottleneck.recommendations.length > 0) {
                doctorContainer.innerHTML = nodeBottleneck.recommendations.map(r => `
                    <div class="doctor-fix-box">
                        <div class="doctor-fix-header">
                            <span>🩺 ${r.category}</span>
                            <span class="doctor-badge-sev ${r.severity}">${r.severity}</span>
                        </div>
                        <div class="doctor-fix-title">${r.title}</div>
                        <div class="doctor-fix-solution">💡 ${r.solution}</div>
                        ${r.code_snippet ? `<pre class="doctor-code-block">${r.code_snippet}</pre>` : ''}
                    </div>
                `).join('');
            } else {
                doctorContainer.innerHTML = `<div style="font-size: 11px; color: var(--text-dim);">No performance issues or latency anomalies detected for this node. Component is operating optimally.</div>`;
            }

            document.getElementById('node-drawer-overlay').style.display = 'flex';
        }

        document.getElementById('btn-close-drawer').addEventListener('click', () => {
            document.getElementById('node-drawer-overlay').style.display = 'none';
        });

        document.getElementById('node-drawer-overlay').addEventListener('click', (e) => {
            if (e.target.id === 'node-drawer-overlay') {
                document.getElementById('node-drawer-overlay').style.display = 'none';
            }
        });

        function focusNode(id) {
            const target = nodes.find(n => n.id === id);
            if (target) {
                panX = -target.x * zoom;
                panY = -target.y * zoom;
                selectedNode = target;
                openNodeDrawer(target);
            }
        }

        // Actions
        document.getElementById('btn-scan').addEventListener('click', async () => {
            const btn = document.getElementById('btn-scan');
            btn.textContent = 'Scanning...';
            try {
                const res = await fetch('{{ route("topology.api.scan") }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                });
                const data = await res.json();
                buildGraph(data.graph);
                fitView();
            } catch (err) {
                console.error(err);
            } finally {
                btn.innerHTML = '<span>⚡</span> Scan Config';
            }
        });

        document.getElementById('btn-clear').addEventListener('click', async () => {
            if (!confirm('Are you sure you want to reset all dynamic telemetry metrics?')) return;
            try {
                const res = await fetch('{{ route("topology.api.clear") }}', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' }
                });
                const data = await res.json();
                buildGraph(data.graph);
                fitView();
            } catch (err) {
                console.error(err);
            }
        });

        document.getElementById('btn-export-json').addEventListener('click', () => {
            window.location.href = '{{ route("topology.api.export") }}';
        });

        async function fetchGraphUpdate() {
            if (!isPolling) return;
            try {
                const res = await fetch('{{ route("topology.api.graph") }}', {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    buildGraph(data);
                }
            } catch (e) {
                // Ignore transient network errors
            }
        }

        function startPolling() {
            pollTimer = setInterval(fetchGraphUpdate, 4000);
        }

        document.getElementById('btn-toggle-poll').addEventListener('click', () => {
            isPolling = !isPolling;
            const label = document.getElementById('poll-label');
            const dot = document.querySelector('#btn-toggle-poll .pulse-circle');
            if (isPolling) {
                label.textContent = 'Live Sync';
                dot.style.background = '#22c55e';
            } else {
                label.textContent = 'Paused';
                dot.style.background = '#ef4444';
            }
        });

        // Launch
        buildGraph(graphData);
        setTimeout(fitView, 120);
        startPolling();
        requestAnimationFrame(render);
    </script>
</body>
</html>
