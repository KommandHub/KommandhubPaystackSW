<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Webhook\Presentation\Listener;

use Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\EntityHandler\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundReader;
use Kommandhub\PaystackSW\Webhook\Domain\Event\RefundPendingEvent;
use Kommandhub\PaystackSW\Webhook\Domain\Event\RefundProcessedEvent;
use Kommandhub\PaystackSW\Webhook\Presentation\Listener\WebhookEventListener;
use Kommandhub\PaystackSW\Webhook\Application\Service\RefundInitializeService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Framework\Context;

#[CoversClass(WebhookEventListener::class)]
#[UsesClass(RefundInitializeService::class)]
class WebhookEventListenerTest extends TestCase
{
    private RefundInitializeService $refundInitializeService;
    private PaymentRefundProcessor $paymentRefundProcessor;
    private OrderTransactionCaptureRefundReader $orderTransactionCaptureRefundReader;
    private WebhookEventListener $listener;

    protected function setUp(): void
    {
        $this->refundInitializeService = $this->createMock(RefundInitializeService::class);
        $this->paymentRefundProcessor = $this->createMock(PaymentRefundProcessor::class);
        $this->orderTransactionCaptureRefundReader = $this->createMock(OrderTransactionCaptureRefundReader::class);

        $this->listener = new WebhookEventListener(
            $this->refundInitializeService,
            $this->paymentRefundProcessor,
            $this->orderTransactionCaptureRefundReader,
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

        $this->orderTransactionCaptureRefundReader->expects($this->once())
            ->method('readIdOfOne')
            ->willReturn('sw_refund_123');

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

        $this->orderTransactionCaptureRefundReader->expects($this->exactly(2))
            ->method('readIdOfOne')
            ->willReturnOnConsecutiveCalls(null, 'sw_refund_123');

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

        $this->orderTransactionCaptureRefundReader->expects($this->never())->method('readIdOfOne');
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

        $this->orderTransactionCaptureRefundReader->expects($this->never())->method('readIdOfOne');
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

        $this->orderTransactionCaptureRefundReader->expects($this->exactly(2))
            ->method('readIdOfOne')
            ->willReturn(null);

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

        $this->orderTransactionCaptureRefundReader->method('readIdOfOne')->willReturn('sw_refund_123');

        $this->paymentRefundProcessor->expects($this->once())
            ->method('processRefund')
            ->willThrowException(new \Exception('Error'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Error');

        $this->listener->onRefundProcessedEvent($event);
    }
}
