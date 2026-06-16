<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Service\Webhook;

use Kommandhub\PaystackSW\Event\Webhook\ChargeSuccessEvent;
use Kommandhub\PaystackSW\Event\Webhook\RefundPendingEvent;
use Kommandhub\PaystackSW\Event\Webhook\RefundProcessedEvent;
use Kommandhub\PaystackSW\Event\Webhook\WebhookEvent;
use Kommandhub\PaystackSW\Service\Webhook\WebhookEventFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(WebhookEventFactory::class)]
#[UsesClass(WebhookEvent::class)]
class WebhookEventFactoryTest extends TestCase
{
    private WebhookEventFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new WebhookEventFactory();
    }

    public function testCreateChargeSuccessEvent(): void
    {
        $event = $this->factory->create('charge.success', [], Context::createDefaultContext());
        $this->assertInstanceOf(ChargeSuccessEvent::class, $event);
    }

    public function testCreateRefundPendingEvent(): void
    {
        $event = $this->factory->create('refund.pending', [], Context::createDefaultContext());
        $this->assertInstanceOf(RefundPendingEvent::class, $event);
    }

    public function testCreateRefundProcessedEvent(): void
    {
        $event = $this->factory->create('refund.processed', [], Context::createDefaultContext());
        $this->assertInstanceOf(RefundProcessedEvent::class, $event);
    }

    public function testCreateReturnsNullOnUnknownEvent(): void
    {
        $event = $this->factory->create('unknown.event', [], Context::createDefaultContext());
        $this->assertNull($event);
    }
}
