<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Event\Webhook;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;
use Symfony\Contracts\EventDispatcher\Event;

class ChargeSuccessEvent extends WebhookEvent
{
    public function getWebhookName(): string
    {
        return 'charge.success';
    }
}
