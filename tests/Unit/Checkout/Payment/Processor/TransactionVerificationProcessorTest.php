<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Processor;

use Kommandhub\PaystackSW\Checkout\Payment\Processor\TransactionVerificationProcessor;
use Kommandhub\PaystackSW\Service\TransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;

#[CoversClass(TransactionVerificationProcessor::class)]
class TransactionVerificationProcessorTest extends TestCase
{
    private TransactionService&MockObject $transactionService;
    private OrderTransactionStateHandler&MockObject $transactionStateHandler;
    private LoggerInterface&MockObject $logger;
    private Context $context;
    private TransactionVerificationProcessor $processor;

    protected function setUp(): void
    {
        $this->transactionService = $this->createMock(TransactionService::class);
        $this->transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->context = Context::createDefaultContext();
        $this->processor = new TransactionVerificationProcessor(
            $this->transactionService,
            $this->transactionStateHandler,
            $this->logger
        );
    }

    public function testVerifySuccess(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'success',
            ],
        ];

        $this->transactionService->expects($this->once())
            ->method('verify')
            ->with($reference)
            ->willReturn($verificationData);

        $result = $this->processor->verify($reference, $transaction, $this->context);

        $this->assertSame($verificationData, $result);
    }

    public function testVerifyThrowsOnApiError(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $this->transactionService->method('verify')->willThrowException(new \Exception('API Error'));

        $this->logger->expects($this->once())->method('error');
        $this->transactionStateHandler->expects($this->once())->method('process')->with('test-id', $this->context);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment verification temporarily failed.');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyThrowsOnFalseStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $verificationData = [
            'status' => false,
            'message' => 'Verification failed message',
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);
        $this->transactionStateHandler->expects($this->once())->method('fail')->with('test-id', $this->context);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Verification failed message');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyHandlesAbandonedStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'abandoned',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);
        $this->transactionStateHandler->expects($this->once())->method('cancel')->with('test-id', $this->context);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The customer abandoned the payment.');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyHandlesFailedStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'failed',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);
        $this->transactionStateHandler->expects($this->once())->method('fail')->with('test-id', $this->context);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('The payment failed or was reversed.');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyHandlesPendingStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'pending',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);
        $this->transactionStateHandler->expects($this->once())->method('process')->with('test-id', $this->context);

        $this->processor->verify($reference, $transaction, $this->context);
    }
}
