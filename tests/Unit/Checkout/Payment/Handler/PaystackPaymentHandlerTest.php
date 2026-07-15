<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Handler;

use Kommandhub\PaystackSW\Checkout\Payment\Handler\PaystackPaymentHandler;
use Kommandhub\PaystackSW\Checkout\Payment\Service\FinalizeProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Service\PaymentProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Service\RefundProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Struct\PaystackInitializationResponse;
use Kommandhub\PaystackSW\Checkout\Payment\Service\OrderTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
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

    public function testPaySuccess(): void
    {
        $context = Context::createDefaultContext();
        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);

        $this->paymentProcessor->expects($this->once())
            ->method('process')
            ->with($transactionStruct, $context)
            ->willReturn(new PaystackInitializationResponse('https://checkout.url', 'test-ref'));

        $response = $this->handler->pay(new Request(), $transactionStruct, $context, null);

        $this->assertEquals('https://checkout.url', $response->getTargetUrl());
    }

    public function testPayThrowsExceptionOnInitializeDecline(): void
    {
        $context = Context::createDefaultContext();
        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);

        $this->paymentProcessor->expects($this->once())
            ->method('process')
            ->willThrowException(PaymentException::asyncProcessInterrupted('id', 'error'));

        $this->expectException(PaymentException::class);
        $this->handler->pay(new Request(), $transactionStruct, $context, null);
    }

    public function testFinalizeSuccess(): void
    {
        $context = Context::createDefaultContext();
        $transactionStruct = $this->createMock(PaymentTransactionStruct::class);
        $request = new Request(['reference' => 'ref123']);

        $this->finalizeProcessor->expects($this->once())
            ->method('process')
            ->with($request, $transactionStruct, $context);

        $this->handler->finalize($request, $transactionStruct, $context);
        $this->assertTrue(true);
    }
}
