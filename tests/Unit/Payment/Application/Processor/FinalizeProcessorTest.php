<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Payment\Application\Processor;

use Kommandhub\PaystackSW\Payment\Application\Processor\FinalizeProcessor;
use Kommandhub\PaystackSW\Payment\Application\Processor\TransactionMetadataProcessorInterface;
use Kommandhub\PaystackSW\Payment\Application\Processor\TransactionVerificationProcessorInterface;
use Kommandhub\PaystackSW\Payment\Application\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Payment\Application\Service\PaymentFinalizedEventService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Kommandhub\PaystackSW\Core\Exception\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentException as ShopwarePaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(FinalizeProcessor::class)]
#[UsesClass(PaymentException::class)]
class FinalizeProcessorTest extends TestCase
{
    private OrderTransactionService&MockObject $orderTransactionService;
    private OrderTransactionStateHandler&MockObject $transactionStateHandler;
    private TransactionVerificationProcessorInterface&MockObject $verificationProcessor;
    private TransactionMetadataProcessorInterface&MockObject $metadataProcessor;
    private PaymentFinalizedEventService&MockObject $paymentFinalizedEventService;
    private LoggerInterface&MockObject $logger;
    private Context $context;
    private FinalizeProcessor $processor;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $this->verificationProcessor = $this->createMock(TransactionVerificationProcessorInterface::class);
        $this->metadataProcessor = $this->createMock(TransactionMetadataProcessorInterface::class);
        $this->paymentFinalizedEventService = $this->createMock(PaymentFinalizedEventService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->context = Context::createDefaultContext();

        $this->processor = new FinalizeProcessor(
            $this->orderTransactionService,
            $this->transactionStateHandler,
            $this->verificationProcessor,
            $this->metadataProcessor,
            $this->paymentFinalizedEventService,
            $this->logger
        );
    }

    public function testProcessSuccess(): void
    {
        $transactionId = 'test-transaction-id';
        $reference = 'test-reference';
        $request = new Request(['reference' => $reference]);

        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $order = $this->createMock(OrderEntity::class);
        $orderTransaction->method('getOrder')->willReturn($order);

        $state = $this->createMock(StateMachineStateEntity::class);
        $state->method('getTechnicalName')->willReturn('open');
        $orderTransaction->method('getStateMachineState')->willReturn($state);

        $this->orderTransactionService->expects($this->once())
            ->method('readOneById')
            ->with($transactionId, $this->context)
            ->willReturn($orderTransaction);

        $verificationData = [
            'data' => [
                'status' => 'success',
                'id' => 'paystack-id',
            ],
        ];
        $this->verificationProcessor->expects($this->once())
            ->method('verify')
            ->with($reference, $orderTransaction, $this->context)
            ->willReturn($verificationData);

        $this->metadataProcessor->expects($this->once())
            ->method('persist')
            ->with($transactionId, $reference, $verificationData, $this->context);

        $this->transactionStateHandler->expects($this->once())
            ->method('paid')
            ->with($transactionId, $this->context);

        $this->paymentFinalizedEventService->expects($this->once())
            ->method('fireEvent')
            ->with($order, $orderTransaction, $transactionStruct, $this->context);

        $this->processor->process($request, $transactionStruct, $this->context);
    }

    public function testProcessThrowsOnMissingReference(): void
    {
        $transactionId = 'test-transaction-id';
        $request = new Request(); // No reference

        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $this->orderTransactionService->method('readOneById')->willReturn($this->createMock(OrderTransactionEntity::class));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Missing Paystack reference.', $this->isType('array'));

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Payment reference is missing from request.');

        $this->processor->process($request, $transactionStruct, $this->context);
    }

    public function testProcessThrowsOnVerificationError(): void
    {
        $transactionId = 'test-transaction-id';
        $reference = 'test-reference';
        $request = new Request(['reference' => $reference]);

        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $this->orderTransactionService->method('readOneById')->willReturn($orderTransaction);

        $this->verificationProcessor->method('verify')
            ->willThrowException(new \Exception('Verification failed'));

        $this->logger->expects($this->never())
            ->method('error');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Verification failed');

        $this->processor->process($request, $transactionStruct, $this->context);
    }

    public function testProcessDoesNotTransitionIfAlreadyPaid(): void
    {
        $transactionId = 'test-transaction-id';
        $reference = 'test-reference';
        $request = new Request(['reference' => $reference]);

        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $state = $this->createMock(StateMachineStateEntity::class);
        $state->method('getTechnicalName')->willReturn('paid');
        $orderTransaction->method('getStateMachineState')->willReturn($state);

        $this->orderTransactionService->method('readOneById')->willReturn($orderTransaction);

        $verificationData = ['data' => ['status' => 'success']];
        $this->verificationProcessor->method('verify')->willReturn($verificationData);

        $this->transactionStateHandler->expects($this->never())->method('paid');

        $this->processor->process($request, $transactionStruct, $this->context);
    }

    public function testProcessThrowsOnUnsuccessfulStatus(): void
    {
        $transactionId = 'test-transaction-id';
        $reference = 'test-reference';
        $request = new Request(['reference' => $reference]);

        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $this->orderTransactionService->method('readOneById')->willReturn($orderTransaction);

        $this->verificationProcessor->method('verify')
            ->willThrowException(PaymentException::asyncFinalizeInterrupted($transactionId, 'Payment failed with status: failed'));

        $this->transactionStateHandler->expects($this->never())->method('paid');

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Payment failed with status: failed');

        $this->processor->process($request, $transactionStruct, $this->context);
    }
}
