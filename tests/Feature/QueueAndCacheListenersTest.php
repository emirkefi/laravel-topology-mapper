<?php

namespace EmirKefi\TopologyMapper\Tests\Feature;

use EmirKefi\TopologyMapper\Listeners\CacheEventListener;
use EmirKefi\TopologyMapper\Listeners\MailEventListener;
use EmirKefi\TopologyMapper\Listeners\QueueLifecycleListener;
use EmirKefi\TopologyMapper\Models\Node;
use EmirKefi\TopologyMapper\Storage\MemoryStorageDriver;
use EmirKefi\TopologyMapper\Support\TraceContext;
use EmirKefi\TopologyMapper\Tests\TestCase;
use Illuminate\Cache\Events\CacheHit;
use Illuminate\Cache\Events\CacheMissed;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\JobQueued;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

class QueueAndCacheListenersTest extends TestCase
{
    public function test_queue_lifecycle_tracks_jobs_and_workers(): void
    {
        $storage = new MemoryStorageDriver();
        $listener = new QueueLifecycleListener($storage);

        TraceContext::start('controller:OrderController', 'POST /orders');

        // 1. Job Queued event
        $queuedEvent = new JobQueued('redis', 'default', 'job-uuid-1', 'App\\Jobs\\ProcessPaymentJob', [], null);
        $listener->handleJobQueued($queuedEvent);

        $nodes = $storage->getNodes();
        $this->assertArrayHasKey('queue:broker:redis', $nodes);
        $this->assertEquals('zone_3', $nodes['queue:broker:redis']->zone);

        $edges = $storage->getEdges();
        $this->assertArrayHasKey('controller:OrderController->queue:broker:redis', $edges);

        // 2. Job Processing event
        $mockJob = \Mockery::mock(\Illuminate\Contracts\Queue\Job::class);
        $mockJob->shouldReceive('getJobId')->andReturn('job-uuid-1');
        $mockJob->shouldReceive('resolveName')->andReturn('App\\Jobs\\ProcessPaymentJob');
        $mockJob->shouldReceive('getQueue')->andReturn('default');
        $mockJob->shouldReceive('attempts')->andReturn(1);

        $processingEvent = new JobProcessing('redis', $mockJob);
        $listener->handleJobProcessing($processingEvent);

        $nodes = $storage->getNodes();
        $this->assertArrayHasKey('worker:job:ProcessPaymentJob', $nodes);

        // 3. Job Processed event
        $processedEvent = new JobProcessed('redis', $mockJob);
        $listener->handleJobProcessed($processedEvent);

        $flows = $storage->getFlows();
        $this->assertNotEmpty($flows);
        $this->assertEquals('worker:job:ProcessPaymentJob', $flows[0]->originNodeId);
    }

    public function test_cache_and_mail_listeners_record_topology_events(): void
    {
        $storage = new MemoryStorageDriver();
        $cacheListener = new CacheEventListener($storage);
        $mailListener = new MailEventListener($storage);

        $cacheListener->handleCacheHit(new CacheHit('redis', 'user_profile_1', ['id' => 1]));
        $cacheListener->handleCacheMiss(new CacheMissed('redis', 'user_profile_2'));

        $nodes = $storage->getNodes();
        $this->assertArrayHasKey('cache:app_store', $nodes);
        $this->assertEquals('zone_2', $nodes['cache:app_store']->zone);

        $message = (new Email())->from('sender@example.com')->to('recipient@example.com')->text('Hello world');
        $envelope = new \Symfony\Component\Mailer\Envelope(
            new \Symfony\Component\Mime\Address('sender@example.com'),
            [new \Symfony\Component\Mime\Address('recipient@example.com')]
        );
        $symfonySentMessage = new \Symfony\Component\Mailer\SentMessage($message, $envelope);
        $sentMessage = new \Illuminate\Mail\SentMessage($symfonySentMessage);
        $mailListener->handleMessageSent(new MessageSent($sentMessage));

        $nodes = $storage->getNodes();
        $this->assertArrayHasKey('mail:smtp', $nodes);
        $this->assertEquals('zone_4', $nodes['mail:smtp']->zone);
    }
}
