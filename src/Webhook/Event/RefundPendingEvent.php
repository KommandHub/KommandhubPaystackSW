<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Event;

class RefundPendingEvent extends WebhookEvent
{
    public function getWebhookName(): string
    {
        return 'refund.pending';
    }
}
