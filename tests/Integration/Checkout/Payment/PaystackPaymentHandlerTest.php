<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Integration\Checkout\Payment;

use Kommandhub\PaystackSW\Checkout\Payment\PaystackPaymentHandler;
use Kommandhub\PaystackSW\Checkout\Payment\Processor\FinalizeProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Processor\PaymentProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Processor\RefundProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Struct\PaystackInitializationResponse;
use Kommandhub\PaystackSW\Service\OrderTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

#[CoversClass(PaystackPaymentHandler::class)]
#[UsesClass(PaystackInitializationResponse::class)]
class PaystackPaymentHandlerTest extends TestCase
{
    private OrderTransactionService $orderTransactionService;
    private PaymentProcessor $paymentProcessor;
    private FinalizeProcessor $finalizeProcessor;
    private RefundProcessor $refundProcessor;
    private PaystackPaymentHandler $handler;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->paymentProcessor = $this->createMock(PaymentProcessor::class);
        $this->finalizeProcessor = $this->createMock(FinalizeProcessor::class);
        $this->refundProcessor = $this->createMock(RefundProcessor::class);

        $this->handler = new PaystackPaymentHandler(
            $this->orderTransactionService,
            $this->paymentProcessor,
            $this->finalizeProcessor,
            $this->refundProcessor
        );
    }

    public function testPaySuccessful(): void
    {
        $context = Context::createDefaultContext();
        $request = new Request();
        $transactionId = 'transaction-id';
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $this->paymentProcessor->expects($this->once())
            ->method('process')
            ->with($transaction, $context)
            ->willReturn(new PaystackInitializationResponse('https://paystack.com/checkout', 'test-ref'));

        $response = $this->handler->pay($request, $transaction, $context, null);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertEquals('https://paystack.com/checkout', $response->getTargetUrl());
    }

    public function testFinalizeSuccessful(): void
    {
        $context = Context::createDefaultContext();
        $transactionId = 'transaction-id';
        $request = new Request(['reference' => 'test_ref']);
        $transaction = new PaymentTransactionStruct($transactionId, 'https://return.url');

        $this->finalizeProcessor->expects($this->once())
            ->method('process')
            ->with($request, $transaction, $context);

        $this->handler->finalize($request, $transaction, $context);
        $this->assertTrue(true);
    }

    public function testRefundSuccessful(): void
    {
        $context = Context::createDefaultContext();
        $transaction = $this->createMock(RefundPaymentTransactionStruct::class);

        $this->refundProcessor->expects($this->once())
            ->method('process')
            ->with($transaction, $context);

        $this->handler->refund($transaction, $context);
        $this->assertTrue(true);
    }

    public function testSupportsReturnsTrueForRefund(): void
    {
        $this->assertTrue($this->handler->supports(PaymentHandlerType::REFUND, 'method-id', Context::createDefaultContext()));
    }

    public function testGetOrderTransaction(): void
    {
        $context = Context::createDefaultContext();
        $transactionId = 'transaction-id';
        $orderTransaction = $this->createMock(\Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity::class);

        $this->orderTransactionService->expects($this->once())
            ->method('get')
            ->with($transactionId, $context)
            ->willReturn($orderTransaction);

        $result = $this->handler->getOrderTransaction($transactionId, $context);
        $this->assertSame($orderTransaction, $result);
    }
}
