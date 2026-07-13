<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Service;

use Kommandhub\PaystackSW\Webhook\Event\ChargeSuccessEvent;
use Kommandhub\PaystackSW\Webhook\Event\RefundPendingEvent;
use Kommandhub\PaystackSW\Webhook\Event\RefundProcessedEvent;
use Shopware\Core\Framework\Context;

class WebhookEventFactory
{
    public function create(string $eventName, array $data, Context $context): ?object
    {
        return match ($eventName) {
            'charge.success' => new ChargeSuccessEvent($data, $context),
            'refund.pending' => new RefundPendingEvent($data, $context),
            'refund.processed' => new RefundProcessedEvent($data, $context),
            default => null,
        };
    }
}
