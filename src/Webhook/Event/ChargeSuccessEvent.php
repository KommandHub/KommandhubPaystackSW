<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Event;

class ChargeSuccessEvent extends WebhookEvent
{
    public function getWebhookName(): string
    {
        return 'charge.success';
    }
}
