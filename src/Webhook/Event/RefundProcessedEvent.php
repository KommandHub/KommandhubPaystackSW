<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Event;

class RefundProcessedEvent extends WebhookEvent
{
    public function getWebhookName(): string
    {
        return 'refund.processed';
    }
}
