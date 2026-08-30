<?php

namespace EmirKefi\TopologyMapper\Http\Controllers;

use EmirKefi\TopologyMapper\Managers\TopologyManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class TopologyDashboardController extends Controller
{
    public function __construct(protected TopologyManager $manager)
    {
    }

    /**
     * Display the visual topology simulator dashboard.
     */
    public function index(): View
    {
        $graph = $this->manager->getEnrichedGraph();

        return view('topology-mapper::dashboard', [
            'initialGraph' => $graph,
            'config' => config('topology'),
        ]);
    }

    /**
     * Fetch updated network topology graph in JSON format.
     */
    public function graph(): JsonResponse
    {
        return response()->json($this->manager->getEnrichedGraph());
    }

    /**
     * Fetch recorded multi-hop data flow traces.
     */
    public function flows(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $flows = $this->manager->getStorage()->getFlows($limit);

        return response()->json([
            'flows' => array_map(fn ($f) => $f->toArray(), $flows),
        ]);
    }

    /**
     * Run on-demand configuration scan.
     */
    public function scan(): JsonResponse
    {
        $this->manager->runStaticScan();

        return response()->json([
            'message' => 'Topology scan completed successfully.',
            'graph' => $this->manager->getEnrichedGraph(),
        ]);
    }

    /**
     * Clear dynamic telemetry metrics and reset to static topology.
     */
    public function clear(): JsonResponse
    {
        $this->manager->clear();

        return response()->json([
            'message' => 'Topology telemetry metrics cleared.',
            'graph' => $this->manager->getEnrichedGraph(),
        ]);
    }

    /**
     * Export network topology snapshot as JSON download.
     */
    public function export(): Response
    {
        $graph = $this->manager->getStorage()->exportGraph();
        $appName = config('topology.app_name', 'laravel-app');
        $fileName = 'topology-' . str_replace(' ', '-', strtolower($appName)) . '-' . date('Y-m-d-His') . '.json';

        return response(
            json_encode($graph, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            200,
            [
                'Content-Type' => 'application/json',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]
        );
    }
}
