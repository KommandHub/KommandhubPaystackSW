<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Domain\Event;

class RefundPendingEvent extends WebhookEvent
{
    public function getWebhookName(): string
    {
        return 'refund.pending';
    }
}
