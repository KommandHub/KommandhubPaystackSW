<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Integration\Administration\Controller;

use Kommandhub\PaystackSW\Administration\Controller\RefundController;
use Kommandhub\PaystackSW\Checkout\Payment\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Client\PaystackClient;
use Kommandhub\PaystackSW\Client\Resource\Refund;
use Kommandhub\PaystackSW\Setting\Service\Config;
use Kommandhub\PaystackSW\Util\PaystackCurrencyHelper;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class RefundControllerTest.
 *
 * This test verifies the RefundController by calling its refund method
 * and asserting the response, similar to how a user would interact with the API.
 */
#[CoversClass(RefundController::class)]
#[UsesClass(PaystackCurrencyHelper::class)]
class RefundControllerTest extends TestCase
{
    private PaystackClient $paystack;
    private Refund $refundResource;
    private OrderTransactionService $orderTransactionService;
    private Config&MockObject $config;
    private RefundController $controller;

    protected function setUp(): void
    {
        $this->paystack = $this->createMock(PaystackClient::class);
        $this->refundResource = $this->createMock(Refund::class);
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->config = $this->createMock(Config::class);

        $this->paystack->method('refunds')->willReturn($this->refundResource);

        $this->controller = new RefundController(
            $this->paystack,
            $this->orderTransactionService,
            $this->config
        );

        $this->config->method('getBool')
            ->with('refundEnabled', $this->anything())
            ->willReturn(true);
    }

    /**
     * Test successful refund request.
     */
    public function testRefundSuccessful(): void
    {
        $payload = [
            'transaction' => 'T12345',
            'amount' => 50, // major units; server converts to 5000 minor (NGN)
            'reason' => 'Customer request',
            'customer_note' => 'Please refund to original source',
            'merchant_note' => 'Approved by support',
        ];

        $request = new Request([], $payload);
        $request->setMethod('POST');
        $context = Context::createDefaultContext();

        $expectedResponse = [
            'status' => true,
            'message' => 'Refund successful',
            'data' => [
                'id' => 123,
                'amount' => 5000,
            ],
        ];

        $this->refundResource->expects($this->once())
            ->method('create')
            ->with([
                'transaction' => 'T12345',
                'amount' => 5000,
                'reason' => 'Customer request',
                'customer_note' => 'Please refund to original source',
                'merchant_note' => 'Approved by support',
            ])
            ->willReturn($expectedResponse);

        $this->orderTransactionService->method('findOneByPaystackReference')
            ->with('T12345', $context)
            ->willReturn($this->createRefundableTransaction());

        $this->config->method('get')->with('minimumRefundAmount')->willReturn(50);

        $response = $this->controller->refund($request, $context);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $this->assertEquals(json_encode($expectedResponse), $response->getContent());
    }

    /**
     * Test refund request fails when transaction reference is missing.
     */
    public function testRefundFailsMissingTransaction(): void
    {
        $payload = [
            'amount' => 5000,
        ];

        $request = new Request([], $payload);
        $request->setMethod('POST');
        $context = Context::createDefaultContext();

        $response = $this->controller->refund($request, $context);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Transaction reference is required', $responseData['error']);
    }

    /**
     * Test refund request fails when Paystack service throws an exception.
     */
    public function testRefundFailsOnServiceException(): void
    {
        $payload = [
            'transaction' => 'T12345',
        ];

        $request = new Request([], $payload);
        $request->setMethod('POST');
        $context = Context::createDefaultContext();

        $this->orderTransactionService->method('findOneByPaystackReference')
            ->with('T12345', $context)
            ->willReturn($this->createRefundableTransaction());

        $this->config->method('get')->with('minimumRefundAmount')->willReturn(50);

        $this->refundResource->expects($this->once())
            ->method('create')
            ->with([
                'transaction' => 'T12345',
                'reason' => 'Refund initiated from shop administration',
            ])
            ->willThrowException(new \Exception('Paystack error'));

        $response = $this->controller->refund($request, $context);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Paystack error', $responseData['error']);
    }

    public function testRefundFailsWhenTransactionIsNotRefundable(): void
    {
        $payload = [
            'transaction' => 'T12345',
        ];

        $request = new Request([], $payload);
        $request->setMethod('POST');
        $context = Context::createDefaultContext();

        $this->orderTransactionService->method('findOneByPaystackReference')
            ->with('T12345', $context)
            ->willReturn(new OrderTransactionEntity());

        $response = $this->controller->refund($request, $context);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Transaction is not in a refundable state', $responseData['error']);
    }

    public function testRefundFailsWhenAmountIsTooLow(): void
    {
        $payload = [
            'transaction' => 'T12345',
            'amount' => 0.49, // Converts to 49 minor units (NGN)
        ];

        $request = new Request([], $payload);
        $request->setMethod('POST');
        $context = Context::createDefaultContext();

        $this->orderTransactionService->method('findOneByPaystackReference')
            ->with('T12345', $context)
            ->willReturn($this->createRefundableTransaction());

        $this->config->method('get')->with('minimumRefundAmount')->willReturn(50);

        $response = $this->controller->refund($request, $context);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Refund amount must be at least 0.5 NGN', $responseData['error']);
    }

    public function testRefundFailsWhenDisabledInConfig(): void
    {
        $payload = [
            'transaction' => 'T12345',
        ];

        $request = new Request([], $payload);
        $request->setMethod('POST');
        $context = Context::createDefaultContext();

        $this->orderTransactionService->method('findOneByPaystackReference')
            ->with('T12345', $context)
            ->willReturn($this->createRefundableTransaction());

        $this->config = $this->createMock(Config::class);
        $this->controller = new RefundController(
            $this->paystack,
            $this->orderTransactionService,
            $this->config
        );

        $this->config->expects($this->once())
            ->method('getBool')
            ->with('refundEnabled', 'sales-channel-id')
            ->willReturn(false);

        $response = $this->controller->refund($request, $context);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Refund feature is currently disabled', $responseData['error']);
    }

    public function testRefundUsesConfiguredMinimumAmount(): void
    {
        $payload = [
            'transaction' => 'T12345',
            'amount' => 0.99, // Converts to 99 minor units (NGN)
        ];

        $request = new Request([], $payload);
        $request->setMethod('POST');
        $context = Context::createDefaultContext();

        $this->orderTransactionService->method('findOneByPaystackReference')
            ->with('T12345', $context)
            ->willReturn($this->createRefundableTransaction());

        // Set minimum to 100 minor units (1.00 NGN)
        $this->config->method('get')->with('minimumRefundAmount')->willReturn(100);

        $response = $this->controller->refund($request, $context);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Refund amount must be at least 1 NGN', $responseData['error']);
    }

    public function testRefundFailsWhenAmountExceedsBalance(): void
    {
        $payload = [
            'transaction' => 'T12345',
            'amount' => 200, // 20000 minor > 10000 refundable balance (100 NGN)
        ];

        $request = new Request([], $payload);
        $request->setMethod('POST');
        $context = Context::createDefaultContext();

        $this->orderTransactionService->method('findOneByPaystackReference')
            ->with('T12345', $context)
            ->willReturn($this->createRefundableTransaction());

        $this->refundResource->expects($this->never())->method('create');

        $response = $this->controller->refund($request, $context);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Refund amount exceeds the refundable balance of 100 NGN', $responseData['error']);
    }

    public function testRefundFailsWithNegativeAmount(): void
    {
        $payload = [
            'transaction' => 'T12345',
            'amount' => -10,
        ];

        $request = new Request([], $payload);
        $request->setMethod('POST');
        $context = Context::createDefaultContext();

        $this->orderTransactionService->method('findOneByPaystackReference')
            ->willReturn($this->createRefundableTransaction());

        $response = $this->controller->refund($request, $context);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Refund amount must be a positive number', $responseData['error']);
    }

    public function testMaxRefundableMinorUnitWithCapturesAndRefunds(): void
    {
        $transaction = $this->createRefundableTransaction();

        $capture1 = new OrderTransactionCaptureEntity();
        $capture1->setId('c1');
        $price1 = $this->createMock(CalculatedPrice::class);
        $price1->method('getTotalPrice')->willReturn(60.0);
        $capture1->setAmount($price1);
        $state1 = new StateMachineStateEntity();
        $state1->setTechnicalName(OrderTransactionCaptureStates::STATE_COMPLETED);
        $capture1->setStateMachineState($state1);

        $refund1 = new OrderTransactionCaptureRefundEntity();
        $refund1->setId('r1');
        $rprice1 = $this->createMock(CalculatedPrice::class);
        $rprice1->method('getTotalPrice')->willReturn(20.0);
        $refund1->setAmount($rprice1);
        $rstate1 = new StateMachineStateEntity();
        $rstate1->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_COMPLETED);
        $refund1->setStateMachineState($rstate1);

        $capture1->setRefunds(new OrderTransactionCaptureRefundCollection([$refund1]));

        $capture2 = new OrderTransactionCaptureEntity();
        $capture2->setId('c2');
        $price2 = $this->createMock(CalculatedPrice::class);
        $price2->method('getTotalPrice')->willReturn(40.0);
        $capture2->setAmount($price2);
        $state2 = new StateMachineStateEntity();
        $state2->setTechnicalName(OrderTransactionCaptureStates::STATE_FAILED);
        $capture2->setStateMachineState($state2);

        $transaction->setCaptures(new OrderTransactionCaptureCollection([$capture1, $capture2]));

        $payload = [
            'transaction' => 'T12345',
            'amount' => 50, // 5000 minor units
        ];
        $request = new Request([], $payload);
        $request->setMethod('POST');
        $context = Context::createDefaultContext();

        $this->orderTransactionService->method('findOneByPaystackReference')
            ->willReturn($transaction);

        // Base is 6000 (capture1) - 2000 (refund1) = 4000. 5000 > 4000
        $response = $this->controller->refund($request, $context);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Refund amount exceeds the refundable balance of 40 NGN', $responseData['error']);
    }

    private function createRefundableTransaction(): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-id');

        $state = new StateMachineStateEntity();
        $state->setTechnicalName(OrderTransactionStates::STATE_PAID);
        $transaction->setStateMachineState($state);

        // No captures yet => refundable base is the transaction total (100 NGN).
        $transaction->setCaptures(new OrderTransactionCaptureCollection());
        $amount = $this->createMock(CalculatedPrice::class);
        $amount->method('getTotalPrice')->willReturn(100.0);
        $transaction->setAmount($amount);

        $currency = new CurrencyEntity();
        $currency->setIsoCode('NGN');
        $order = new OrderEntity();
        $order->setCurrency($currency);
        $order->setSalesChannelId('sales-channel-id');
        $transaction->setOrder($order);

        return $transaction;
    }
}
