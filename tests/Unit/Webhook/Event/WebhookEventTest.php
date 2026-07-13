<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Webhook\Event;

use Kommandhub\PaystackSW\Webhook\Event\ChargeSuccessEvent;
use Kommandhub\PaystackSW\Webhook\Event\RefundPendingEvent;
use Kommandhub\PaystackSW\Webhook\Event\RefundProcessedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(ChargeSuccessEvent::class)]
#[CoversClass(RefundPendingEvent::class)]
#[CoversClass(RefundProcessedEvent::class)]
class WebhookEventTest extends TestCase
{
    public function testEvents(): void
    {
        $data = ['test' => 'data'];
        $context = Context::createDefaultContext();

        $chargeSuccessEvent = new ChargeSuccessEvent($data, $context);
        $this->assertEquals('charge.success', $chargeSuccessEvent->getWebhookName());
        $this->assertEquals($data, $chargeSuccessEvent->getData());
        $this->assertEquals($context, $chargeSuccessEvent->getContext());

        $refundPendingEvent = new RefundPendingEvent($data, $context);
        $this->assertEquals('refund.pending', $refundPendingEvent->getWebhookName());
        $this->assertEquals($data, $refundPendingEvent->getData());
        $this->assertEquals($context, $refundPendingEvent->getContext());

        $refundProcessedEvent = new RefundProcessedEvent($data, $context);
        $this->assertEquals('refund.processed', $refundProcessedEvent->getWebhookName());
        $this->assertEquals($data, $refundProcessedEvent->getData());
        $this->assertEquals($context, $refundProcessedEvent->getContext());
    }
}
