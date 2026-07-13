<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Service;

use Doctrine\DBAL\Connection;
use Kommandhub\PaystackSW\Checkout\Payment\Service\RefundAggregationResult;
use Kommandhub\PaystackSW\Checkout\Payment\Service\RefundAggregator;
use Kommandhub\PaystackSW\Checkout\Payment\Service\RefundProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Service\OrderTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;

#[CoversClass(RefundProcessor::class)]
#[UsesClass(RefundAggregationResult::class)]
class RefundProcessorTest extends TestCase
{
    private OrderTransactionService&MockObject $orderTransactionService;
    private OrderTransactionCaptureRefundStateHandler&MockObject $refundStateHandler;
    private OrderTransactionCaptureStateHandler&MockObject $captureStateHandler;
    private OrderTransactionStateHandler&MockObject $transactionStateHandler;
    private RefundAggregator&MockObject $aggregator;
    private Connection&MockObject $connection;
    private Context $context;
    private RefundProcessor $processor;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->refundStateHandler = $this->createMock(OrderTransactionCaptureRefundStateHandler::class);
        $this->captureStateHandler = $this->createMock(OrderTransactionCaptureStateHandler::class);
        $this->transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $this->aggregator = $this->createMock(RefundAggregator::class);
        $this->connection = $this->createMock(Connection::class);
        $this->context = Context::createDefaultContext();

        $this->processor = new RefundProcessor(
            $this->orderTransactionService,
            $this->refundStateHandler,
            $this->captureStateHandler,
            $this->transactionStateHandler,
            $this->aggregator,
            $this->connection
        );
    }

    public function testProcessSuccess(): void
    {
        $orderTransactionId = 'transaction-id';
        $refundId = 'refund-1';
        $captureId = 'capture-1';

        $struct = $this->createMock(RefundPaymentTransactionStruct::class);
        $struct->method('getOrderTransactionId')
            ->willReturn($orderTransactionId);
        $struct->method('getRefundId')
            ->willReturn($refundId);

        $refundState = $this->createMock(StateMachineStateEntity::class);
        $refundState->method('getTechnicalName')
            ->willReturn(OrderTransactionCaptureRefundStates::STATE_FAILED);

        $refund = $this->createMock(OrderTransactionCaptureRefundEntity::class);
        $refund->method('getId')->willReturn($refundId);
        $refund->method('getUniqueIdentifier')->willReturn($refundId);
        $refund->method('getStateMachineState')->willReturn($refundState);

        $captureState = $this->createMock(StateMachineStateEntity::class);
        $captureState->method('getTechnicalName')
            ->willReturn(OrderTransactionCaptureStates::STATE_FAILED);

        $capture = $this->createMock(OrderTransactionCaptureEntity::class);
        $capture->method('getId')->willReturn($captureId);
        $capture->method('getUniqueIdentifier')->willReturn($captureId);
        $capture->method('getRefunds')
            ->willReturn(new OrderTransactionCaptureRefundCollection([$refund]));
        $capture->method('getStateMachineState')
            ->willReturn($captureState);

        $capturesCollection = new OrderTransactionCaptureCollection([$capture]);

        $transactionState = $this->createMock(StateMachineStateEntity::class);
        $transactionState->method('getTechnicalName')
            ->willReturn(OrderTransactionStates::STATE_FAILED);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($orderTransactionId);
        $orderTransaction->method('getCaptures')->willReturn($capturesCollection);
        $orderTransaction->method('getStateMachineState')
            ->willReturn($transactionState);

        $this->orderTransactionService->expects($this->once())
            ->method('readOneById')
            ->with($orderTransactionId, $this->context)
            ->willReturn($orderTransaction);

        $this->connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(
                static fn (callable $callback) => $callback()
            );

        $this->refundStateHandler->expects($this->once())
            ->method('reopen')
            ->with($refundId, $this->context);

        $this->refundStateHandler->expects($this->once())
            ->method('complete')
            ->with($refundId, $this->context);

        $aggregationResult = new RefundAggregationResult(
            [$captureId => (object)['isFullyRefunded' => true]],
            true
        );

        $this->aggregator->expects($this->once())
            ->method('aggregate')
            ->with($orderTransaction, $refundId)
            ->willReturn($aggregationResult);

        $this->captureStateHandler->expects($this->once())
            ->method('complete')
            ->with($captureId, $this->context);

        $this->transactionStateHandler->expects($this->once())
            ->method('reopen')
            ->with($orderTransactionId, $this->context);

        $this->transactionStateHandler->expects($this->once())
            ->method('refund')
            ->with($orderTransactionId, $this->context);

        $this->processor->process($struct, $this->context);
    }

    public function testProcessThrowsOnMissingRefundId(): void
    {
        $struct = $this->createMock(RefundPaymentTransactionStruct::class);
        $struct->method('getOrderTransactionId')
            ->willReturn('transaction-id');
        $struct->method('getRefundId')
            ->willReturn('');

        $this->orderTransactionService
            ->expects($this->never())
            ->method('readOneById');

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Missing refund identifier.');

        $this->processor->process($struct, $this->context);
    }

    public function testProcessThrowsOnRefundNotFound(): void
    {
        $orderTransactionId = 'transaction-id';
        $refundId = 'refund-1';

        $struct = $this->createMock(RefundPaymentTransactionStruct::class);
        $struct->method('getOrderTransactionId')
            ->willReturn($orderTransactionId);
        $struct->method('getRefundId')
            ->willReturn($refundId);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getCaptures')
            ->willReturn(new OrderTransactionCaptureCollection());

        $this->orderTransactionService->expects($this->once())
            ->method('readOneById')
            ->with($orderTransactionId, $this->context)
            ->willReturn($orderTransaction);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Refund not found.');

        $this->processor->process($struct, $this->context);
    }

    public function testProcessPartialRefundWithFailedCapture(): void
    {
        $orderTransactionId = 'transaction-id';
        $refundId = 'refund-1';
        $captureId = 'capture-1';

        $struct = $this->createMock(RefundPaymentTransactionStruct::class);
        $struct->method('getOrderTransactionId')
            ->willReturn($orderTransactionId);
        $struct->method('getRefundId')
            ->willReturn($refundId);

        $refundState = $this->createMock(StateMachineStateEntity::class);
        $refundState->method('getTechnicalName')->willReturn(OrderTransactionCaptureRefundStates::STATE_OPEN);

        $refund = $this->createMock(OrderTransactionCaptureRefundEntity::class);
        $refund->method('getId')->willReturn($refundId);
        $refund->method('getUniqueIdentifier')->willReturn($refundId);
        $refund->method('getStateMachineState')->willReturn($refundState);

        $captureState = $this->createMock(StateMachineStateEntity::class);
        $captureState->method('getTechnicalName')->willReturn(OrderTransactionCaptureStates::STATE_FAILED);

        $capture = $this->createMock(OrderTransactionCaptureEntity::class);
        $capture->method('getId')->willReturn($captureId);
        $capture->method('getUniqueIdentifier')->willReturn($captureId);
        $capture->method('getRefunds')->willReturn(new OrderTransactionCaptureRefundCollection([$refund]));
        $capture->method('getStateMachineState')->willReturn($captureState);

        $transactionState = $this->createMock(StateMachineStateEntity::class);
        $transactionState->method('getTechnicalName')->willReturn(OrderTransactionStates::STATE_OPEN);

        $capturesCollection = new OrderTransactionCaptureCollection([$capture]);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($orderTransactionId);
        $orderTransaction->method('getCaptures')->willReturn($capturesCollection);
        $orderTransaction->method('getStateMachineState')->willReturn($transactionState);

        $this->orderTransactionService->expects($this->any())
            ->method('readOneById')
            ->with($orderTransactionId, $this->context)
            ->willReturn($orderTransaction);

        $this->connection->method('transactional')
            ->willReturnCallback(function (callable $cb) {
                return $cb();
            });

        // Aggregator returns partial refund
        $aggregationResult = new RefundAggregationResult(
            [$captureId => (object)['isFullyRefunded' => true]],
            false // isFullyRefunded = false
        );

        $this->aggregator->expects($this->once())
            ->method('aggregate')
            ->with($orderTransaction, $refundId)
            ->willReturn($aggregationResult);

        // Capture is fully refunded AND failed, so it should be reopened then completed
        $this->captureStateHandler->expects($this->once())
            ->method('reopen')
            ->with($captureId, $this->context);

        $this->captureStateHandler->expects($this->once())
            ->method('complete')
            ->with($captureId, $this->context);

        // Transaction is partially refunded
        $this->transactionStateHandler->expects($this->once())
            ->method('refundPartially')
            ->with($orderTransactionId, $this->context);

        $this->transactionStateHandler->expects($this->never())
            ->method('refund');

        $this->processor->process($struct, $this->context);
    }
}
