<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment;

use Kommandhub\PaystackSW\Checkout\Payment\PaystackPaymentHandler;
use Kommandhub\PaystackSW\Service\Config;
use Kommandhub\PaystackSW\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Service\PayloadBuilder;
use Kommandhub\PaystackSW\Service\PaymentFinalizedEventService;
use Kommandhub\PaystackSW\Service\TransactionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
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

    public function testPaySuccess(): void
    {
        $transactionId = 'order-transaction-id';
        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $this->payloadBuilder->method('build')->willReturn(['foo' => 'bar']);
        $this->transactionService->method('initialize')->willReturn([
            'status' => true,
            'data' => ['authorization_url' => 'https://checkout.url'],
        ]);

        $response = $this->handler->pay(new Request(), $transactionStruct, Context::createDefaultContext(), null);

        $this->assertEquals('https://checkout.url', $response->getTargetUrl());
    }

    public function testPayThrowsExceptionOnInitializeDecline(): void
    {
        $transactionId = 'id';
        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);
        $this->orderTransactionService->method('get')->willReturn($this->createMock(OrderTransactionEntity::class));
        $this->payloadBuilder->method('build')->willReturn([]);
        $this->transactionService->method('initialize')->willReturn(['status' => false, 'message' => 'error']);

        $this->expectException(PaymentException::class);
        $this->handler->pay(new Request(), $transactionStruct, Context::createDefaultContext(), null);
    }

    public function testFinalizeSuccess(): void
    {
        $transactionId = 'id';
        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $transactionStruct->method('getOrderTransactionId')->willReturn($transactionId);

        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $orderTransaction->method('getId')->willReturn($transactionId);
        $this->orderTransactionService->method('get')->willReturn($orderTransaction);

        $request = new Request(['reference' => 'ref123']);

        $this->transactionService->method('verify')->willReturn([
            'status' => true,
            'data' => ['status' => 'success', 'id' => 'ps123'],
        ]);

        $this->transactionStateHandler->expects($this->once())->method('paid');

        $this->handler->finalize($request, $transactionStruct, Context::createDefaultContext());
    }
}
