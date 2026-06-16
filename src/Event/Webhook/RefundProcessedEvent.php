<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Event\Webhook;

class RefundProcessedEvent extends WebhookEvent
{
    public function getWebhookName(): string
    {
        return 'refund.processed';
    }
}
