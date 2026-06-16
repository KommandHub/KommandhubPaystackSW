<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Event\Webhook;

class ChargeSuccessEvent extends WebhookEvent
{
    public function getWebhookName(): string
    {
        return 'charge.success';
    }
}
