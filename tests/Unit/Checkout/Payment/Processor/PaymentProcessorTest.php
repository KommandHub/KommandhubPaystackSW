<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Processor;

use Kommandhub\PaystackSW\Checkout\Payment\Processor\PaymentProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Struct\PaystackInitializationResponse;
use Kommandhub\PaystackSW\Exceptions\PaystackException;
use Kommandhub\PaystackSW\Service\Entity\OrderTransactionService;
use Kommandhub\PaystackSW\Service\PayloadBuilder;
use Kommandhub\PaystackSW\Service\TransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;

#[CoversClass(PaymentProcessor::class)]
#[UsesClass(PaystackInitializationResponse::class)]
class PaymentProcessorTest extends TestCase
{
    private OrderTransactionService&MockObject $orderTransactionService;
    private PayloadBuilder&MockObject $payloadBuilder;
    private TransactionService&MockObject $transactionService;
    private LoggerInterface&MockObject $logger;
    private Context $context;
    private PaymentProcessor $processor;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->payloadBuilder = $this->createMock(PayloadBuilder::class);
        $this->transactionService = $this->createMock(TransactionService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->context = Context::createDefaultContext();

        $this->processor = new PaymentProcessor(
            $this->orderTransactionService,
            $this->payloadBuilder,
            $this->transactionService,
            $this->logger
        );
    }

    public function testProcessSuccess(): void
    {
        $transactionId = 'test-transaction-id';
        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $this->orderTransactionService->expects($this->once())
            ->method('readOneById')
            ->with($transactionId, $this->context)
            ->willReturn($orderTransaction);

        $payload = ['email' => 'test@example.com', 'amount' => 10000];
        $this->payloadBuilder->expects($this->once())
            ->method('build')
            ->with($orderTransaction, $transactionStruct)
            ->willReturn($payload);

        $response = [
            'status' => true,
            'data' => [
                'authorization_url' => 'https://checkout.paystack.com/test',
                'reference' => 'test-reference',
                'access_code' => 'test-access-code',
            ],
        ];
        $this->transactionService->expects($this->once())
            ->method('initialize')
            ->with($payload)
            ->willReturn($response);

        $result = $this->processor->process($transactionStruct, $this->context);

        $this->assertInstanceOf(PaystackInitializationResponse::class, $result);
        $this->assertSame('https://checkout.paystack.com/test', $result->getAuthorizationUrl());
        $this->assertSame('test-reference', $result->getReference());
        $this->assertSame('test-access-code', $result->getAccessCode());
    }

    public function testProcessThrowsOnPayloadBuilderRuntimeException(): void
    {
        $transactionId = 'test-transaction-id';
        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $this->orderTransactionService->method('readOneById')->willReturn($orderTransaction);

        $this->payloadBuilder->method('build')->willThrowException(new \RuntimeException('Build failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Failed to build Paystack payment payload.', $this->isType('array'));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Unable to prepare payment payload: Build failed');

        $this->processor->process($transactionStruct, $this->context);
    }

    public function testProcessThrowsOnPaystackException(): void
    {
        $transactionId = 'test-transaction-id';
        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $this->orderTransactionService->method('readOneById')->willReturn($orderTransaction);

        $this->payloadBuilder->method('build')->willReturn([]);
        $this->transactionService->method('initialize')->willThrowException(new PaystackException('Communication failed'));

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Paystack communication error during initialization.', $this->isType('array'));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('A communication error occurred with the payment gateway');

        $this->processor->process($transactionStruct, $this->context);
    }

    public function testProcessThrowsOnDeclinedInitialization(): void
    {
        $transactionId = 'test-transaction-id';
        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $this->orderTransactionService->method('readOneById')->willReturn($this->createMock(OrderTransactionEntity::class));
        $this->payloadBuilder->method('build')->willReturn([]);

        $response = [
            'status' => false,
            'message' => 'Invalid amount',
        ];
        $this->transactionService->method('initialize')->willReturn($response);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Paystack declined to initialize the payment: Invalid amount');

        $this->processor->process($transactionStruct, $this->context);
    }

    public function testProcessThrowsOnMissingDataInResponse(): void
    {
        $transactionId = 'test-transaction-id';
        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $this->orderTransactionService->method('readOneById')->willReturn($this->createMock(OrderTransactionEntity::class));
        $this->payloadBuilder->method('build')->willReturn([]);

        $response = [
            'status' => true,
            'data' => [
                'authorization_url' => '', // Empty URL
                'reference' => 'test-reference',
            ],
        ];
        $this->transactionService->method('initialize')->willReturn($response);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Paystack did not return a valid checkout URL or reference');

        $this->processor->process($transactionStruct, $this->context);
    }
}
