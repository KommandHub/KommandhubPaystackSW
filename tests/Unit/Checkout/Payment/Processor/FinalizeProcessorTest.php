<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Processor;

use Kommandhub\PaystackSW\Checkout\Payment\Processor\FinalizeProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Processor\TransactionMetadataProcessorInterface;
use Kommandhub\PaystackSW\Checkout\Payment\Processor\TransactionVerificationProcessorInterface;
use Kommandhub\PaystackSW\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Service\PaymentFinalizedEventService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(FinalizeProcessor::class)]
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
            ->method('get')
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

        $this->orderTransactionService->method('get')->willReturn($this->createMock(OrderTransactionEntity::class));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Missing Paystack reference.', $this->isType('array'));

        $this->expectException(PaymentException::class);
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
        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $this->verificationProcessor->method('verify')
            ->willThrowException(new \Exception('Verification failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Paystack verification failed.', $this->isType('array'));

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

        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $verificationData = ['data' => ['status' => 'success']];
        $this->verificationProcessor->method('verify')->willReturn($verificationData);

        $this->transactionStateHandler->expects($this->never())->method('paid');

        $this->processor->process($request, $transactionStruct, $this->context);
    }
}
