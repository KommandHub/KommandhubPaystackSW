<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Service;

use Kommandhub\PaystackSW\Event\PaystackPaymentFinalizedEvent;
use Kommandhub\PaystackSW\Service\PaymentFinalizedEventService;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class PaymentFinalizedEventServiceTest extends TestCase
{
    public function testFireEvent(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $service = new PaymentFinalizedEventService($eventDispatcher);

        $order = $this->createMock(OrderEntity::class);
        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $paymentTransactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $context = Context::createDefaultContext();

        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PaystackPaymentFinalizedEvent::class));

        $service->fireEvent($order, $orderTransaction, $paymentTransactionStruct, $context);
    }
}
