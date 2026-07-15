<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Event;

use Kommandhub\PaystackSW\Checkout\Payment\Event\PaymentFinalizedEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;

#[CoversClass(PaymentFinalizedEvent::class)]
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

        $event = new PaymentFinalizedEvent($order, $transaction, $struct, $context);

        $this->assertSame($order, $event->getOrder());
        $this->assertSame($transaction, $event->getOrderTransaction());
        $this->assertSame($struct, $event->getPaymentTransactionStruct());
        $this->assertSame($context, $event->getContext());
    }
}
