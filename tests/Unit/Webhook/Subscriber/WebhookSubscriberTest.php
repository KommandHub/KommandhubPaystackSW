<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Webhook\Subscriber;

use Kommandhub\PaystackSW\Webhook\Service\RefundInitializeService;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Kommandhub\PaystackSW\Webhook\Event\RefundPendingEvent;
use Kommandhub\PaystackSW\Webhook\Event\RefundProcessedEvent;
use Kommandhub\PaystackSW\Util\OrderCurrencyResolver;
use Kommandhub\PaystackSW\Util\PaystackCurrencyHelper;
use Kommandhub\PaystackSW\Webhook\Subscriber\WebhookSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;

#[CoversClass(WebhookSubscriber::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(RefundInitializeService::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(PaystackCurrencyHelper::class)]
#[\PHPUnit\Framework\Attributes\UsesClass(OrderCurrencyResolver::class)]
class WebhookSubscriberTest extends TestCase
{
    private RefundInitializeService $refundInitializeService;
    private PaymentRefundProcessor $paymentRefundProcessor;
    private EntityRepository $orderTransactionCaptureRefundRepository;
    private ConfigurableLogger $logger;
    private WebhookSubscriber $listener;

    protected function setUp(): void
    {
        $this->refundInitializeService = $this->createMock(RefundInitializeService::class);
        $this->paymentRefundProcessor = $this->createMock(PaymentRefundProcessor::class);
        $this->orderTransactionCaptureRefundRepository = $this->createMock(EntityRepository::class);
        $this->logger = $this->createMock(ConfigurableLogger::class);

        $this->listener = new WebhookSubscriber(
            $this->refundInitializeService,
            $this->paymentRefundProcessor,
            $this->orderTransactionCaptureRefundRepository,
            $this->logger
        );
    }

    public function testOnRefundPendingEvent(): void
    {
        $context = Context::createDefaultContext();
        $data = ['id' => 123];
        $event = $this->createMock(RefundPendingEvent::class);
        $event->method('getWebhookName')->willReturn('refund.pending');
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        $this->refundInitializeService->expects($this->once())
            ->method('handle')
            ->with($data, $context);

        $this->listener->onRefundPendingEvent($event);
    }

    public function testOnRefundProcessedEvent(): void
    {
        $context = Context::createDefaultContext();
        $data = [
            'id' => 'ref_123',
            'transaction_reference' => 'T123',
            'amount' => 1000, // 10.00 NGN — matches the refund fixture
        ];
        $event = $this->createMock(RefundProcessedEvent::class);
        $event->method('getWebhookName')->willReturn('refund.processed');
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        $refund = $this->createRefundEntity('sw_refund_123', OrderTransactionCaptureRefundStates::STATE_OPEN);

        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createRefundSearchResult($refund));

        $this->paymentRefundProcessor->expects($this->once())
            ->method('processRefund')
            ->with('sw_refund_123', $context);

        $this->listener->onRefundProcessedEvent($event);
    }

    public function testOnRefundProcessedEventAlreadyCompleted(): void
    {
        $context = Context::createDefaultContext();
        $data = [
            'id' => 'ref_123',
            'transaction_reference' => 'T123',
            'amount' => 1000, // 10.00 NGN — matches the refund fixture
        ];
        $event = $this->createMock(RefundProcessedEvent::class);
        $event->method('getWebhookName')->willReturn('refund.processed');
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        $refund = $this->createRefundEntity('sw_refund_123', OrderTransactionCaptureRefundStates::STATE_COMPLETED);

        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createRefundSearchResult($refund));

        $this->refundInitializeService->expects($this->never())->method('handle');
        $this->paymentRefundProcessor->expects($this->never())->method('processRefund');

        $this->listener->onRefundProcessedEvent($event);
    }

    public function testOnRefundProcessedEventInitializesIfNotFound(): void
    {
        $context = Context::createDefaultContext();
        $data = [
            'id' => 'ref_123',
            'transaction_reference' => 'T123',
            'amount' => 1000, // 10.00 NGN — matches the refund fixture
        ];
        $event = $this->createMock(RefundProcessedEvent::class);
        $event->method('getWebhookName')->willReturn('refund.processed');
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        $refund = $this->createRefundEntity('sw_refund_123', OrderTransactionCaptureRefundStates::STATE_OPEN);

        $this->orderTransactionCaptureRefundRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->createRefundSearchResult(null),
                $this->createRefundSearchResult($refund)
            );

        $this->refundInitializeService->expects($this->once())
            ->method('handle')
            ->with($data, $context);

        $this->paymentRefundProcessor->expects($this->once())
            ->method('processRefund')
            ->with('sw_refund_123', $context);

        $this->listener->onRefundProcessedEvent($event);
    }

    public function testOnRefundProcessedEventMissingTransactionReference(): void
    {
        $context = Context::createDefaultContext();
        $data = ['id' => 'ref_123'];
        $event = $this->createMock(RefundProcessedEvent::class);
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        $this->orderTransactionCaptureRefundRepository->expects($this->never())->method('search');
        $this->paymentRefundProcessor->expects($this->never())->method('processRefund');

        $this->listener->onRefundProcessedEvent($event);
    }

    public function testOnRefundProcessedEventMissingRefundId(): void
    {
        $context = Context::createDefaultContext();
        $data = ['transaction_reference' => 'T123'];
        $event = $this->createMock(RefundProcessedEvent::class);
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        $this->orderTransactionCaptureRefundRepository->expects($this->never())->method('search');
        $this->paymentRefundProcessor->expects($this->never())->method('processRefund');

        $this->listener->onRefundProcessedEvent($event);
    }

    public function testOnRefundProcessedEventNotFoundAfterInitialization(): void
    {
        $context = Context::createDefaultContext();
        $data = [
            'id' => 'ref_123',
            'transaction_reference' => 'T123',
            'amount' => 1000, // 10.00 NGN — matches the refund fixture
        ];
        $event = $this->createMock(RefundProcessedEvent::class);
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        $this->orderTransactionCaptureRefundRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                $this->createRefundSearchResult(null),
                $this->createRefundSearchResult(null)
            );

        $this->refundInitializeService->expects($this->once())->method('handle');
        $this->paymentRefundProcessor->expects($this->never())->method('processRefund');

        $this->listener->onRefundProcessedEvent($event);
    }

    public function testOnRefundProcessedEventThrowsException(): void
    {
        $context = Context::createDefaultContext();
        $data = [
            'id' => 'ref_123',
            'transaction_reference' => 'T123',
            'amount' => 1000, // 10.00 NGN — matches the refund fixture
        ];
        $event = $this->createMock(RefundProcessedEvent::class);
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        $refund = $this->createRefundEntity('sw_refund_123', OrderTransactionCaptureRefundStates::STATE_OPEN);

        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createRefundSearchResult($refund));

        $this->paymentRefundProcessor->expects($this->once())
            ->method('processRefund')
            ->willThrowException(new \Exception('Error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error');

        $this->listener->onRefundProcessedEvent($event);
    }

    public function testOnRefundProcessedEventRejectsAmountMismatch(): void
    {
        $context = Context::createDefaultContext();
        $data = [
            'id' => 'ref_123',
            'transaction_reference' => 'T123',
            'amount' => 500, // 5.00 NGN, but the Shopware refund is for 10.00
        ];
        $event = $this->createMock(RefundProcessedEvent::class);
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        $refund = $this->createRefundEntity('sw_refund_123', OrderTransactionCaptureRefundStates::STATE_OPEN, 10.00);

        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createRefundSearchResult($refund));

        $this->logger->expects($this->once())
            ->method('error')
            ->with(
                $this->stringContains('does not match the refund being processed'),
                $this->isType('array')
            );

        $this->paymentRefundProcessor->expects($this->never())->method('processRefund');

        $this->listener->onRefundProcessedEvent($event);
    }

    public function testOnRefundProcessedEventAbortsWhenCurrencyCannotBeResolved(): void
    {
        $context = Context::createDefaultContext();
        $data = [
            'id' => 'ref_123',
            'transaction_reference' => 'T123',
            'amount' => 1000,
        ];
        $event = $this->createMock(RefundProcessedEvent::class);
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        // Refund without a resolvable capture -> transaction -> order -> currency.
        $refund = new OrderTransactionCaptureRefundEntity();
        $refund->setId('sw_refund_123');
        $refund->setAmount(new CalculatedPrice(10.00, 10.00, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $state = new StateMachineStateEntity();
        $state->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_OPEN);
        $refund->setStateMachineState($state);

        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createRefundSearchResult($refund));

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Unable to resolve the order currency'), $this->isType('array'));

        $this->paymentRefundProcessor->expects($this->never())->method('processRefund');

        $this->listener->onRefundProcessedEvent($event);
    }

    public function testOnRefundProcessedEventRejectsMissingAmount(): void
    {
        $context = Context::createDefaultContext();
        $data = [
            'id' => 'ref_123',
            'transaction_reference' => 'T123',
            // no amount -> cannot be verified, must not be finalized
        ];
        $event = $this->createMock(RefundProcessedEvent::class);
        $event->method('getData')->willReturn($data);
        $event->method('getContext')->willReturn($context);

        $refund = $this->createRefundEntity('sw_refund_123', OrderTransactionCaptureRefundStates::STATE_OPEN);

        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('search')
            ->willReturn($this->createRefundSearchResult($refund));

        $this->logger->expects($this->once())->method('error');
        $this->paymentRefundProcessor->expects($this->never())->method('processRefund');

        $this->listener->onRefundProcessedEvent($event);
    }

    private function createRefundEntity(string $id, string $state, float $amount = 10.00, string $currencyIso = 'NGN'): OrderTransactionCaptureRefundEntity
    {
        $refund = new OrderTransactionCaptureRefundEntity();
        $refund->setId($id);
        $refund->setAmount(new CalculatedPrice(
            $amount,
            $amount,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        ));

        $stateEntity = new StateMachineStateEntity();
        $stateEntity->setTechnicalName($state);
        $refund->setStateMachineState($stateEntity);

        // Full association chain: the subscriber resolves the order currency
        // through capture -> transaction -> order and fails closed without it.
        $currency = new CurrencyEntity();
        $currency->setIsoCode($currencyIso);
        $order = new OrderEntity();
        $order->setCurrency($currency);
        $transaction = new OrderTransactionEntity();
        $transaction->setId('trans-id-123');
        $transaction->setOrder($order);
        $capture = new OrderTransactionCaptureEntity();
        $capture->setId('capture-id-1');
        $capture->setTransaction($transaction);
        $refund->setTransactionCapture($capture);

        return $refund;
    }

    private function createRefundSearchResult(?OrderTransactionCaptureRefundEntity $refund): EntitySearchResult
    {
        $entities = [];

        if ($refund !== null) {
            $entities[] = $refund;
        }

        return new EntitySearchResult(
            OrderTransactionCaptureRefundEntity::class,
            count($entities),
            new OrderTransactionCaptureRefundCollection($entities),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
