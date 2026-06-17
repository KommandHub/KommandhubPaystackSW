<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Payment\Application\Service;

use Kommandhub\PaystackSW\Payment\Domain\Event\PaystackPaymentFinalizedEvent;
use Kommandhub\PaystackSW\Payment\Application\Service\PaymentFinalizedEventService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[CoversClass(PaymentFinalizedEventService::class)]
class PaymentFinalizedEventServiceTest extends TestCase
{
    public function testFireEvent(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $service = new PaymentFinalizedEventService($dispatcher);

        $order = $this->createMock(OrderEntity::class);
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $struct = $this->createMock(PaymentTransactionStruct::class);
        $context = Context::createDefaultContext();

        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(PaystackPaymentFinalizedEvent::class));

        $service->fireEvent($order, $transaction, $struct, $context);
    }
}
