<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Domain\Event;

class ChargeSuccessEvent extends WebhookEvent
{
    public function getWebhookName(): string
    {
        return 'charge.success';
    }
}
