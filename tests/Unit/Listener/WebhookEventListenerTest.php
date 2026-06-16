<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Listener;

use Kommandhub\PaystackSW\Event\Webhook\RefundPendingEvent;
use Kommandhub\PaystackSW\Event\Webhook\RefundProcessedEvent;
use Kommandhub\PaystackSW\Listener\WebhookEventListener;
use Kommandhub\PaystackSW\Service\Webhook\RefundInitializeService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

#[CoversClass(WebhookEventListener::class)]
#[UsesClass(RefundInitializeService::class)]
class WebhookEventListenerTest extends TestCase
{
    private RefundInitializeService $refundInitializeService;
    private PaymentRefundProcessor $paymentRefundProcessor;
    private EntityRepository $orderTransactionCaptureRefundRepository;
    private WebhookEventListener $listener;

    protected function setUp(): void
    {
        $this->refundInitializeService = $this->createMock(RefundInitializeService::class);
        $this->paymentRefundProcessor = $this->createMock(PaymentRefundProcessor::class);
        $this->orderTransactionCaptureRefundRepository = $this->createMock(EntityRepository::class);

        $this->listener = new WebhookEventListener(
            $this->refundInitializeService,
            $this->paymentRefundProcessor,
            $this->orderTransactionCaptureRefundRepository,
            $this->createMock(LoggerInterface::class)
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

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn('sw_refund_123');

        $this->orderTransactionCaptureRefundRepository->expects($this->once())
            ->method('searchIds')
            ->willReturn($idSearchResult);

        $this->paymentRefundProcessor->expects($this->once())
            ->method('processRefund')
            ->with('sw_refund_123', $context);

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

        $idSearchResultNone = $this->createMock(IdSearchResult::class);
        $idSearchResultNone->method('firstId')->willReturn(null);

        $idSearchResultFound = $this->createMock(IdSearchResult::class);
        $idSearchResultFound->method('firstId')->willReturn('sw_refund_123');

        $this->orderTransactionCaptureRefundRepository->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturnOnConsecutiveCalls($idSearchResultNone, $idSearchResultFound);

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

        $this->orderTransactionCaptureRefundRepository->expects($this->never())->method('searchIds');
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

        $this->orderTransactionCaptureRefundRepository->expects($this->never())->method('searchIds');
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

        $idSearchResultNone = $this->createMock(IdSearchResult::class);
        $idSearchResultNone->method('firstId')->willReturn(null);

        $this->orderTransactionCaptureRefundRepository->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturn($idSearchResultNone);

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

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')->willReturn('sw_refund_123');

        $this->orderTransactionCaptureRefundRepository->method('searchIds')->willReturn($idSearchResult);

        $this->paymentRefundProcessor->expects($this->once())
            ->method('processRefund')
            ->willThrowException(new \Exception('Error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error');

        $this->listener->onRefundProcessedEvent($event);
    }
}
