<?php

namespace EmirKefi\TopologyMapper\Listeners;

use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Models\DataFlowPath;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;
use EmirKefi\TopologyMapper\Support\TraceContext;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Str;

class QueueLifecycleListener
{
    /** @var array<string, float> */
    protected static array $jobStartTimes = [];

    public function __construct(protected StorageDriverInterface $storage)
    {
    }

    public function handleJobQueued(JobQueued $event): void
    {
        $connection = $event->connectionName ?? 'default';
        $queueName = $event->job ?? 'default';
        $queueNodeId = "queue:broker:{$connection}";

        // 1. Record Queue Broker Node (Zone 3)
        $queueNode = new Node(
            id: $queueNodeId,
            label: "Queue Broker ({$connection})",
            type: 'queue',
            zone: 'zone_3',
            driver: $connection
        );
        $queueNode->recordMetrics(1.0, true);
        $this->storage->recordNode($queueNode);

        // 2. Record Edge from Origin (Controller/Command) to Queue Broker
        $sourceId = TraceContext::getOriginNodeId() ?? 'app:core';
        $jobName = is_object($event->job) ? get_class($event->job) : (string) $event->job;
        $jobShortName = class_basename($jobName);
        $operation = "Dispatch {$jobShortName}";

        $edge = new Edge(
            source: $sourceId,
            target: $queueNodeId,
            protocol: 'queue',
            operation: $operation
        );
        $edge->recordMetrics(1.0, true, $operation);
        $this->storage->recordEdge($edge);

        // 3. Add Hop
        TraceContext::addHop($queueNodeId, 'queue', $operation, 1.0, true, [
            'job' => $jobName,
            'queue' => $queueName,
        ]);
    }

    public function handleJobProcessing(JobProcessing $event): void
    {
        $jobId = $event->job->getJobId() ?: spl_object_hash($event->job);
        self::$jobStartTimes[$jobId] = microtime(true);

        $jobName = $event->job->resolveName();
        $jobShortName = class_basename($jobName);
        $workerNodeId = "worker:job:{$jobShortName}";

        // Start trace for worker context
        TraceContext::start($workerNodeId, "Queue Worker ({$jobShortName})", 'queue_job');

        // Record Worker Node (Zone 3)
        $workerNode = new Node(
            id: $workerNodeId,
            label: "Worker ({$jobShortName})",
            type: 'worker',
            zone: 'zone_3',
            driver: $event->connectionName,
            metadata: [
                'full_class' => $jobName,
                'queue' => $event->job->getQueue(),
                'attempts' => $event->job->attempts(),
            ]
        );
        $this->storage->recordNode($workerNode);

        // Edge from Queue Broker to Worker
        $brokerNodeId = "queue:broker:{$event->connectionName}";
        $edge = new Edge(
            source: $brokerNodeId,
            target: $workerNodeId,
            protocol: 'queue',
            operation: "Consume {$jobShortName}"
        );
        $edge->recordMetrics(1.0, true, "Consume {$jobShortName}");
        $this->storage->recordEdge($edge);
    }

    public function handleJobProcessed(JobProcessed $event): void
    {
        $jobId = $event->job->getJobId() ?: spl_object_hash($event->job);
        $startTime = self::$jobStartTimes[$jobId] ?? null;
        unset(self::$jobStartTimes[$jobId]);

        $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : 0.0;
        $jobName = $event->job->resolveName();
        $jobShortName = class_basename($jobName);
        $workerNodeId = "worker:job:{$jobShortName}";

        // Update Worker Node metrics
        $node = new Node(
            id: $workerNodeId,
            label: "Worker ({$jobShortName})",
            type: 'worker',
            zone: 'zone_3'
        );
        $node->recordMetrics($durationMs, true);
        $this->storage->recordNode($node);

        // Save recorded flow
        if (TraceContext::getTraceId()) {
            $flow = new DataFlowPath(
                traceId: TraceContext::getTraceId(),
                originNodeId: $workerNodeId,
                originLabel: "Job: {$jobShortName}",
                originType: 'queue_job',
                durationMs: $durationMs,
                hops: TraceContext::getHops(),
                success: true
            );
            $this->storage->recordFlow($flow);
        }

        TraceContext::reset();
    }

    public function handleJobFailed(JobFailed $event): void
    {
        $jobId = $event->job->getJobId() ?: spl_object_hash($event->job);
        $startTime = self::$jobStartTimes[$jobId] ?? null;
        unset(self::$jobStartTimes[$jobId]);

        $durationMs = $startTime ? (microtime(true) - $startTime) * 1000 : 0.0;
        $jobName = $event->job->resolveName();
        $jobShortName = class_basename($jobName);
        $workerNodeId = "worker:job:{$jobShortName}";

        $node = new Node(
            id: $workerNodeId,
            label: "Worker ({$jobShortName})",
            type: 'worker',
            zone: 'zone_3'
        );
        $node->recordMetrics($durationMs, false);
        $this->storage->recordNode($node);

        if (TraceContext::getTraceId()) {
            $flow = new DataFlowPath(
                traceId: TraceContext::getTraceId(),
                originNodeId: $workerNodeId,
                originLabel: "Job: {$jobShortName} (Failed)",
                originType: 'queue_job',
                durationMs: $durationMs,
                hops: TraceContext::getHops(),
                success: false
            );
            $this->storage->recordFlow($flow);
        }

        TraceContext::reset();
    }
}
