<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Service;

use Kommandhub\PaystackSW\Checkout\Payment\Service\TransactionVerificationProcessor;
use Kommandhub\PaystackSW\Exception\PaymentException;
use Kommandhub\PaystackSW\Checkout\Payment\Service\TransactionService;
use Kommandhub\PaystackSW\Util\PaystackCurrencyHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentException as ShopwarePaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;

#[CoversClass(TransactionVerificationProcessor::class)]
#[UsesClass(PaystackCurrencyHelper::class)]
#[UsesClass(PaymentException::class)]
class TransactionVerificationProcessorTest extends TestCase
{
    private TransactionService&MockObject $transactionService;
    private OrderTransactionStateHandler&MockObject $transactionStateHandler;
    private ConfigurableLogger&MockObject $logger;
    private Context $context;
    private TransactionVerificationProcessor $processor;

    protected function setUp(): void
    {
        $this->transactionService = $this->createMock(TransactionService::class);
        $this->transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $this->logger = $this->createMock(ConfigurableLogger::class);
        $this->context = Context::createDefaultContext();
        $this->processor = new TransactionVerificationProcessor(
            $this->transactionService,
            $this->logger
        );
    }

    public function testVerifySuccess(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'success',
                'amount' => 2000,
                'currency' => 'NGN',
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

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Payment verification temporarily unavailable.');

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

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Verification failed message');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyHandlesAbandonedStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'abandoned',
                'amount' => 2000,
                'currency' => 'NGN',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);

        $this->logger->expects($this->once())->method('info');

        $this->assertSame($verificationData, $this->processor->verify($reference, $transaction, $this->context));
    }

    public function testVerifyHandlesFailedStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'failed',
                'amount' => 2000,
                'currency' => 'NGN',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Payment failed with status: failed');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyHandlesPendingStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'pending',
                'amount' => 2000,
                'currency' => 'NGN',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);

        $this->logger->expects($this->once())->method('info');

        $this->assertSame($verificationData, $this->processor->verify($reference, $transaction, $this->context));
    }

    public function testVerifyHandlesProcessingStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'processing',
                'amount' => 2000,
                'currency' => 'NGN',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);
        $this->logger->expects($this->once())->method('info');

        $this->assertSame($verificationData, $this->processor->verify($reference, $transaction, $this->context));
    }

    public function testVerifyThrowsOnMissingData(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $verificationData = [
            'status' => true,
            // 'data' is missing
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Missing Paystack transaction data.');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyThrowsOnInvalidAmount(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $verificationData = [
            'status' => true,
            'data' => [
                'amount' => 'invalid',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Invalid or missing Paystack amount.');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyThrowsOnMissingOrder(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');
        $transaction->method('getOrder')->willReturn(null);

        $verificationData = [
            'status' => true,
            'data' => [
                'amount' => 2000,
                'currency' => 'NGN',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Missing order currency information.');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyThrowsOnAmountMismatch(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        $verificationData = [
            'status' => true,
            'data' => [
                'amount' => 3000, // Mismatch
                'status' => 'success',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);
        $this->logger->expects($this->once())->method('warning');

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Payment amount mismatch detected.');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyThrowsOnCurrencyMismatch(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        // Amount collides (2000 minor units) but the Paystack currency differs.
        $verificationData = [
            'status' => true,
            'data' => [
                'amount' => 2000,
                'currency' => 'USD',
                'status' => 'success',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);
        $this->logger->expects($this->once())->method('warning');

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Payment currency mismatch detected.');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyThrowsOnMissingOrderCurrencyInCurrencyAssertion(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        // Currency is missing
        $order->method('getCurrency')->willReturn(null);
        $transaction->method('getOrder')->willReturn($order);

        $verificationData = [
            'status' => true,
            'data' => [
                'amount' => 2000,
                'currency' => 'NGN',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);

        // This will actually fail at line 119 in assertTransactionAmount first
        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Missing order currency information.');

        try {
            $this->processor->verify($reference, $transaction, $this->context);
        } catch (ShopwarePaymentException $e) {
            $this->assertStringContainsString('Missing order currency information.', $e->getMessage());
            throw $e;
        }
    }

    public function testAssertTransactionCurrencyThrowsOnMissingOrder(): void
    {
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');
        $transaction->method('getOrder')->willReturn(null);

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Missing order currency information.');

        $method = new \ReflectionMethod(TransactionVerificationProcessor::class, 'assertTransactionCurrency');
        $method->invoke($this->processor, ['currency' => 'NGN'], $transaction);
    }

    public function testVerifyHandlesReversedStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'reversed',
                'amount' => 2000,
                'currency' => 'NGN',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Payment failed with status: reversed');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyHandlesQueuedStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'queued',
                'amount' => 2000,
                'currency' => 'NGN',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);
        $this->logger->expects($this->once())->method('info');

        $this->assertSame($verificationData, $this->processor->verify($reference, $transaction, $this->context));
    }

    public function testVerifyHandlesOngoingStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        $verificationData = [
            'status' => true,
            'data' => [
                'status' => 'ongoing',
                'amount' => 2000,
                'currency' => 'NGN',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);
        $this->logger->expects($this->once())->method('info');

        $this->assertSame($verificationData, $this->processor->verify($reference, $transaction, $this->context));
    }

    public function testFail(): void
    {
        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Test error');

        $method = new \ReflectionMethod(TransactionVerificationProcessor::class, 'fail');
        $method->invoke($this->processor, 'tid', 'Test error');
    }

    public function testVerifyThrowsOnMissingStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        $verificationData = [
            'status' => true,
            'data' => [
                'amount' => 2000,
                'currency' => 'NGN',
                // status missing
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Missing Paystack transaction status.');

        $this->processor->verify($reference, $transaction, $this->context);
    }

    public function testVerifyThrowsOnUnknownStatus(): void
    {
        $reference = 'test-reference';
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $transaction->method('getId')->willReturn('test-id');

        $order = $this->createMock(OrderEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $currency->method('getIsoCode')->willReturn('NGN');
        $order->method('getCurrency')->willReturn($currency);
        $transaction->method('getOrder')->willReturn($order);

        $calculatedPrice = $this->createMock(CalculatedPrice::class);
        $calculatedPrice->method('getTotalPrice')->willReturn(20.00);
        $transaction->method('getAmount')->willReturn($calculatedPrice);

        $verificationData = [
            'status' => true,
            'data' => [
                'amount' => 2000,
                'currency' => 'NGN',
                'status' => 'unknown-status',
            ],
        ];

        $this->transactionService->method('verify')->willReturn($verificationData);

        $this->expectException(ShopwarePaymentException::class);
        $this->expectExceptionMessage('Unknown Paystack status: unknown-status');

        $this->processor->verify($reference, $transaction, $this->context);
    }
}
