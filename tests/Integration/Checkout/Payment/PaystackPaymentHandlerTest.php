<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Integration\Checkout\Payment;

use Kommandhub\PaystackSW\Checkout\Payment\PaystackPaymentHandler;
use Kommandhub\PaystackSW\Service\Config;
use Kommandhub\PaystackSW\Exceptions\PaystackException;
use Kommandhub\PaystackSW\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Service\PayloadBuilder;
use Kommandhub\PaystackSW\Service\PaymentFinalizedEventService;
use Kommandhub\PaystackSW\Service\TransactionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class PaystackPaymentHandlerTest extends TestCase
{
    private OrderTransactionService $orderTransactionService;
    private TransactionService $transactionService;
    private PayloadBuilder $payloadBuilder;
    private OrderTransactionStateHandler $transactionStateHandler;
    private Config $config;
    private PaymentFinalizedEventService $paymentFinalizedEventService;
    private LoggerInterface $logger;
    private PaystackPaymentHandler $handler;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->transactionService = $this->createMock(TransactionService::class);
        $this->payloadBuilder = $this->createMock(PayloadBuilder::class);
        $this->transactionStateHandler = $this->createMock(OrderTransactionStateHandler::class);
        $this->config = $this->createMock(Config::class);
        $this->paymentFinalizedEventService = $this->createMock(PaymentFinalizedEventService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new PaystackPaymentHandler(
            $this->orderTransactionService,
            $this->transactionService,
            $this->payloadBuilder,
            $this->transactionStateHandler,
            $this->config,
            $this->paymentFinalizedEventService,
            $this->logger
        );
    }

    public function testPaySuccessful(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request();
        $transactionId = 'transaction-id';
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);

        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $payload = ['amount' => 10000];
        $this->payloadBuilder->method('build')->willReturn($payload);

        $paystackResponse = [
            'status' => true,
            'data' => [
                'authorization_url' => 'https://paystack.com/checkout',
            ],
        ];
        $this->transactionService->method('initialize')->willReturn($paystackResponse);

        $response = $this->handler->pay($request, $transaction, $context, null);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('https://paystack.com/checkout', $response->getTargetUrl());
    }

    public function testPayFailsInitialization(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request();
        $transactionId = 'transaction-id';
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);

        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $payload = ['amount' => 10000];
        $this->payloadBuilder->method('build')->willReturn($payload);

        $paystackResponse = [
            'status' => false,
            'message' => 'Invalid API key',
        ];
        $this->transactionService->method('initialize')->willReturn($paystackResponse);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Paystack declined to initialize the payment: Invalid API key');

        $this->handler->pay($request, $transaction, $context, null);
    }

    public function testFinalizeSuccessful(): void
    {
        $context = Context::createDefaultContext();
        $transactionId = 'transaction-id';
        $request = new Request(['reference' => 'test_ref']);
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $order = $this->createMock(OrderEntity::class);
        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);
        $orderTransaction->method('getOrder')->willReturn($order);

        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $verificationResult = [
            'status' => true,
            'data' => [
                'id' => 123,
                'channel' => 'card',
                'fees' => 150,
                'amount' => 10000,
                'currency' => 'NGN',
            ],
        ];
        $this->transactionService->method('verify')->with('test_ref')->willReturn($verificationResult);

        $this->transactionStateHandler->expects($this->once())->method('paid');
        $this->orderTransactionService->expects($this->once())->method('updateCustomFields');
        $this->paymentFinalizedEventService->expects($this->once())->method('fireEvent')
            ->with($order, $orderTransaction, $transaction, $context);

        $this->handler->finalize($request, $transaction, $context);
    }

    public function testFinalizeFailsWithoutReference(): void
    {
        $context = Context::createDefaultContext();
        $transactionId = 'transaction-id';
        $request = new Request();
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);

        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Payment reference is missing from the request.');

        $this->handler->finalize($request, $transaction, $context);
    }

    public function testSupportsReturnsFalse(): void
    {
        $this->assertFalse($this->handler->supports(PaymentHandlerType::RECURRING, 'method-id', Context::createDefaultContext()));
    }

    public function testPayFailsWithRuntimeException(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request();
        $transactionId = 'transaction-id';
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);
        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $this->payloadBuilder->method('build')->willThrowException(new \RuntimeException('Build failed'));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Build failed');

        $this->handler->pay($request, $transaction, $context, null);
    }

    public function testPayFailsWithPaystackException(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request();
        $transactionId = 'transaction-id';
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);
        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $this->payloadBuilder->method('build')->willReturn(['payload']);
        $this->transactionService->method('initialize')->willThrowException(new PaystackException('Paystack error'));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('A communication error occurred with the payment gateway');

        $this->handler->pay($request, $transaction, $context, null);
    }

    public function testFinalizeFailsWhenVerificationStatusIsFalse(): void
    {
        $context = Context::createDefaultContext();
        $transactionId = 'transaction-id';
        $request = new Request(['reference' => 'test_ref']);
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);
        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $this->transactionService->method('verify')->willReturn(['status' => false, 'message' => 'Verification failed']);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Unable to verify payment: Verification failed');

        $this->handler->finalize($request, $transaction, $context);
    }

    public function testGetOrderTransactionThrowsWhenNotFound(): void
    {
        $context = Context::createDefaultContext();
        $transactionId = 'non-existent-id';

        $this->orderTransactionService->method('get')->willThrowException(PaymentException::asyncProcessInterrupted($transactionId, 'not found'));

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('not found');

        $this->handler->getOrderTransaction($transactionId, $context);
    }

    public function testLoggingEnabledPaths(): void
    {
        $this->config->method('getBool')->willReturn(true);
        $this->logger->expects($this->atLeastOnce())->method('info');

        $context = Context::createDefaultContext();
        $request = new Request(['reference' => 'test_ref']);
        $transactionId = 'transaction-id';
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);
        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $this->payloadBuilder->method('build')->willReturn(['payload']);
        $this->transactionService->method('initialize')->willReturn(['status' => true, 'data' => ['authorization_url' => 'http://url']]);
        $this->transactionService->method('verify')->willReturn(['status' => true, 'data' => ['id' => 'paystack_id']]);

        $this->handler->pay($request, $transaction, $context, null);
        $this->handler->finalize($request, $transaction, $context);
    }

    public function testLoggingErrorPaths(): void
    {
        $this->config->method('getBool')->willReturn(true);
        $this->logger->expects($this->atLeastOnce())->method('error');

        $context = Context::createDefaultContext();
        $transactionId = 'transaction-id';
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);
        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        // RuntimeException in pay
        $this->payloadBuilder->method('build')->willThrowException(new \RuntimeException('Build failed'));

        try {
            $this->handler->pay(new Request(), $transaction, $context, null);
        } catch (PaymentException) {
        }

        // PaystackException in pay
        $this->payloadBuilder->method('build')->willReturn(['payload']);
        $this->transactionService->method('initialize')->willThrowException(new PaystackException('Paystack error'));

        try {
            $this->handler->pay(new Request(), $transaction, $context, null);
        } catch (PaymentException) {
        }

        // Missing reference in finalize
        try {
            $this->handler->finalize(new Request(), $transaction, $context);
        } catch (PaymentException) {
        }

        // Verification failed in finalize
        $this->transactionService->method('verify')->willReturn(['status' => false, 'message' => 'Verification failed']);

        try {
            $this->handler->finalize(new Request(['reference' => 'ref']), $transaction, $context);
        } catch (PaymentException) {
        }
    }

    public function testFinalizeSuccessfulWithMissingOrderInTransaction(): void
    {
        $context = Context::createDefaultContext();
        $transactionId = 'transaction-id';
        $request = new Request(['reference' => 'test_ref']);
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);
        $orderTransaction->method('getOrder')->willReturn(null);

        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $verificationResult = [
            'status' => true,
            'data' => [
                'id' => 123,
                'channel' => 'card',
                'fees' => 150,
                'amount' => 10000,
                'currency' => 'NGN',
            ],
        ];
        $this->transactionService->method('verify')->with('test_ref')->willReturn($verificationResult);

        $this->transactionStateHandler->expects($this->once())->method('paid');
        $this->orderTransactionService->expects($this->once())->method('updateCustomFields');

        // This should not call fireEvent because order is null
        $this->paymentFinalizedEventService->expects($this->never())->method('fireEvent');

        $this->handler->finalize($request, $transaction, $context);
    }

    public function testPayFailsWhenAuthorizationUrlIsMissing(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request();
        $transactionId = 'transaction-id';
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);
        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $this->payloadBuilder->method('build')->willReturn(['payload']);
        $this->transactionService->method('initialize')->willReturn(['status' => true, 'data' => []]);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage('Paystack did not return a checkout URL');

        $this->handler->pay($request, $transaction, $context, null);
    }

    public function testLoggingDisabled(): void
    {
        $this->config->method('getBool')->willReturn(false);
        $this->logger->expects($this->never())->method('info');
        $this->logger->expects($this->never())->method('error');

        $context = Context::createDefaultContext();
        $request = new Request(['reference' => 'test_ref']);
        $transactionId = 'transaction-id';
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);
        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $this->payloadBuilder->method('build')->willReturn(['payload']);
        $this->transactionService->method('initialize')->willReturn(['status' => true, 'data' => ['authorization_url' => 'http://url']]);
        $this->transactionService->method('verify')->willReturn(['status' => true, 'data' => ['id' => 'paystack_id']]);

        $this->handler->pay($request, $transaction, $context, null);
        $this->handler->finalize($request, $transaction, $context);

        // Also trigger an error path with debugging disabled
        $this->transactionService->method('verify')->willReturn(['status' => false, 'message' => 'failed']);

        try {
            $this->handler->finalize($request, $transaction, $context);
        } catch (PaymentException) {
        }
    }

    public function testPersistTransactionMetadataWithMissingData(): void
    {
        $context = Context::createDefaultContext();
        $transactionId = 'transaction-id';
        $request = new Request(['reference' => 'test_ref']);
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);
        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        // Verification data with missing optional fields
        $verificationResult = [
            'status' => true,
            'data' => [
                // 'id' missing
                // 'channel' missing
                // 'fees' missing
                // 'amount' missing
                // 'currency' missing
            ],
        ];
        $this->transactionService->method('verify')->willReturn($verificationResult);

        $this->orderTransactionService->expects($this->once())
            ->method('updateCustomFields')
            ->with(
                $transactionId,
                $this->callback(function (array $fields) {
                    return array_key_exists('paystack_transaction_id', $fields) && $fields['paystack_transaction_id'] === null
                        && array_key_exists('paystack_payment_type', $fields) && $fields['paystack_payment_type'] === null
                        && array_key_exists('paystack_transaction_fee', $fields) && $fields['paystack_transaction_fee'] === null
                        && array_key_exists('paystack_amount', $fields) && $fields['paystack_amount'] === null
                        && array_key_exists('paystack_currency', $fields) && $fields['paystack_currency'] === null;
                }),
                $context
            );

        $this->handler->finalize($request, $transaction, $context);
    }
}
