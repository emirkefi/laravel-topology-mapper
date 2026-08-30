<?php

namespace EmirKefi\TopologyMapper\Contracts;

use EmirKefi\TopologyMapper\Models\DataFlowPath;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;

interface StorageDriverInterface
{
    /**
     * Store or update a topology node.
     */
    public function recordNode(Node $node): void;

    /**
     * Store or update a topology directed edge.
     */
    public function recordEdge(Edge $edge): void;

    /**
     * Record an end-to-end data flow path trace.
     */
    public function recordFlow(DataFlowPath $flow): void;

    /**
     * Retrieve all active topology nodes.
     *
     * @return array<string, Node>
     */
    public function getNodes(): array;

    /**
     * Retrieve all active topology edges.
     *
     * @return array<string, Edge>
     */
    public function getEdges(): array;

    /**
     * Retrieve recorded data flow paths.
     *
     * @return array<int, DataFlowPath>
     */
    public function getFlows(int $limit = 50): array;

    /**
     * Clear all recorded topology data.
     */
    public function clear(): void;

    /**
     * Export full topology state as an associative array.
     *
     * @return array<string, mixed>
     */
    public function exportGraph(): array;
}
