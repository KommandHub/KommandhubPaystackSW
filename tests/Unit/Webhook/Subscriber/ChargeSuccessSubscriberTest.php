<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Webhook\Subscriber;

use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Kommandhub\PaystackSW\Checkout\Payment\Service\FinalizeProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Webhook\Event\ChargeSuccessEvent;
use Kommandhub\PaystackSW\Webhook\Subscriber\ChargeSuccessSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(ChargeSuccessSubscriber::class)]
class ChargeSuccessSubscriberTest extends TestCase
{
    private OrderTransactionService $orderTransactionService;
    private FinalizeProcessor $finalizeProcessor;
    private ConfigurableLogger $logger;
    private ChargeSuccessSubscriber $listener;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->finalizeProcessor = $this->createMock(FinalizeProcessor::class);
        $this->logger = $this->createMock(ConfigurableLogger::class);

        $this->listener = new ChargeSuccessSubscriber(
            $this->orderTransactionService,
            $this->finalizeProcessor,
            $this->logger
        );
    }

    public function testOnChargeSuccessEventFinalizesMatchingTransaction(): void
    {
        $context = Context::createDefaultContext();
        $data = ['reference' => 'paystack-ref-123'];
        $event = $this->createMock(ChargeSuccessEvent::class);
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-id');

        $this->orderTransactionService->expects($this->once())
            ->method('findOneByPaystackReference')
            ->with('paystack-ref-123', $context)
            ->willReturn($transaction);

        $this->finalizeProcessor->expects($this->once())
            ->method('process')
            ->with(
                $this->callback(fn (Request $request): bool => $request->query->getString('reference') === 'paystack-ref-123'),
                $this->callback(fn (PaymentTransactionStruct $struct): bool => $struct->getOrderTransactionId() === 'transaction-id'),
                $context,
                $transaction
            );

        $this->listener->onChargeSuccessEvent($event);
    }

    public function testOnChargeSuccessEventLogsWhenReferenceMissing(): void
    {
        $event = $this->createMock(ChargeSuccessEvent::class);
        $event->method('getData')->willReturn([]);
        $event->method('getContext')->willReturn(Context::createDefaultContext());

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('missing a reference'));

        $this->orderTransactionService->expects($this->never())->method('findOneByPaystackReference');
        $this->finalizeProcessor->expects($this->never())->method('process');

        $this->listener->onChargeSuccessEvent($event);
    }

    public function testOnChargeSuccessEventLogsWhenTransactionMissing(): void
    {
        $context = Context::createDefaultContext();
        $event = $this->createMock(ChargeSuccessEvent::class);
        $event->method('getData')->willReturn(['reference' => 'paystack-ref-123']);
        $event->method('getContext')->willReturn($context);

        $this->orderTransactionService->expects($this->once())
            ->method('findOneByPaystackReference')
            ->with('paystack-ref-123', $context)
            ->willReturn(null);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Could not find transaction'));

        $this->finalizeProcessor->expects($this->never())->method('process');

        $this->listener->onChargeSuccessEvent($event);
    }
}
