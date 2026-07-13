<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Webhook\Subscriber;

use Kommandhub\PaystackSW\Webhook\Service\RefundInitializeService;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Kommandhub\PaystackSW\Webhook\Event\RefundPendingEvent;
use Kommandhub\PaystackSW\Webhook\Event\RefundProcessedEvent;
use Kommandhub\PaystackSW\Webhook\Subscriber\WebhookSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
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
class WebhookSubscriberTest extends TestCase
{
    private RefundInitializeService $refundInitializeService;
    private PaymentRefundProcessor $paymentRefundProcessor;
    private EntityRepository $orderTransactionCaptureRefundRepository;
    private WebhookSubscriber $listener;

    protected function setUp(): void
    {
        $this->refundInitializeService = $this->createMock(RefundInitializeService::class);
        $this->paymentRefundProcessor = $this->createMock(PaymentRefundProcessor::class);
        $this->orderTransactionCaptureRefundRepository = $this->createMock(EntityRepository::class);

        $this->listener = new WebhookSubscriber(
            $this->refundInitializeService,
            $this->paymentRefundProcessor,
            $this->orderTransactionCaptureRefundRepository,
            $this->createMock(ConfigurableLogger::class)
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

    private function createRefundEntity(string $id, string $state): OrderTransactionCaptureRefundEntity
    {
        $refund = new OrderTransactionCaptureRefundEntity();
        $refund->setId($id);

        $stateEntity = new StateMachineStateEntity();
        $stateEntity->setTechnicalName($state);
        $refund->setStateMachineState($stateEntity);

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
