<?php

namespace EmirKefi\TopologyMapper\Listeners;

use EmirKefi\TopologyMapper\Contracts\StorageDriverInterface;
use EmirKefi\TopologyMapper\Models\Edge;
use EmirKefi\TopologyMapper\Models\Node;
use EmirKefi\TopologyMapper\Support\TraceContext;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

class MailEventListener
{
    public function __construct(protected StorageDriverInterface $storage)
    {
    }

    public function handleMessageSent(MessageSent $event): void
    {
        $mailer = config('mail.default', 'smtp');
        $mailNodeId = "mail:{$mailer}";

        $node = new Node(
            id: $mailNodeId,
            label: "Mailer ({$mailer})",
            type: 'mail',
            zone: 'zone_4',
            driver: $mailer
        );
        $node->recordMetrics(10.0, true);
        $this->storage->recordNode($node);

        $sourceId = TraceContext::getOriginNodeId() ?? 'app:core';
        $operation = 'Send Email';

        $edge = new Edge(
            source: $sourceId,
            target: $mailNodeId,
            protocol: 'smtp',
            operation: $operation
        );
        $edge->recordMetrics(10.0, true, $operation);
        $this->storage->recordEdge($edge);

        TraceContext::addHop($mailNodeId, 'smtp', $operation, 10.0, true);
    }
}
