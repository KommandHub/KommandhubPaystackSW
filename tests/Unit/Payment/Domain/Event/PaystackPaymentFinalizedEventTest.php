<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Payment\Domain\Event;

use Kommandhub\PaystackSW\Payment\Domain\Event\PaystackPaymentFinalizedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;

#[CoversClass(PaystackPaymentFinalizedEvent::class)]
class PaystackPaymentFinalizedEventTest extends TestCase
{
    public function testEvent(): void
    {
        $order = new OrderEntity();
        $order->setId('order-id');

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-id');

        $struct = $this->createMock(PaymentTransactionStruct::class);

        $context = Context::createDefaultContext();

        $event = new PaystackPaymentFinalizedEvent($order, $transaction, $struct, $context);

        $this->assertSame($order, $event->getOrder());
        $this->assertSame($transaction, $event->getOrderTransaction());
        $this->assertSame($struct, $event->getPaymentTransactionStruct());
        $this->assertSame($context, $event->getContext());
    }
}
