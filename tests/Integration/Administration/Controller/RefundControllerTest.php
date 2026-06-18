<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Integration\Administration\Controller;

use Kommandhub\PaystackSW\Administration\Controller\RefundController;
use Kommandhub\PaystackSW\Payment\Application\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Paystack;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class RefundControllerTest.
 *
 * This test verifies the RefundController by calling its refund method
 * and asserting the response, similar to how a user would interact with the API.
 */
#[CoversClass(RefundController::class)]
class RefundControllerTest extends TestCase
{
    private Paystack $paystack;
    private Refund $refundResource;
    private OrderTransactionService $orderTransactionService;
    private RefundController $controller;

    protected function setUp(): void
    {
        $this->paystack = $this->createMock(Paystack::class);
        $this->refundResource = $this->createMock(Refund::class);
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);

        $this->paystack->method('refunds')->willReturn($this->refundResource);

        $this->controller = new RefundController($this->paystack, $this->orderTransactionService);
    }

    /**
     * Test successful refund request.
     */
    public function testRefundSuccessful(): void
    {
        $payload = [
            'transaction' => 'T12345',
            'amount' => 5000,
            'reason' => 'Customer request',
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
            ])
            ->willReturn($expectedResponse);

        $this->orderTransactionService->method('findOneByPaystackReference')
            ->with('T12345', $context)
            ->willReturn($this->createRefundableTransaction());

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

    private function createRefundableTransaction(): OrderTransactionEntity
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-id');

        $state = new StateMachineStateEntity();
        $state->setTechnicalName(OrderTransactionStates::STATE_PAID);
        $transaction->setStateMachineState($state);

        $capture = new OrderTransactionCaptureEntity();
        $capture->setId('capture-id');
        $captureState = new StateMachineStateEntity();
        $captureState->setTechnicalName(OrderTransactionCaptureStates::STATE_COMPLETED);
        $capture->setStateMachineState($captureState);
        $capture->setRefunds(new OrderTransactionCaptureRefundCollection([
            $this->createRefundEntity(),
        ]));

        $transaction->setCaptures(new OrderTransactionCaptureCollection([$capture]));

        return $transaction;
    }

    private function createRefundEntity(): OrderTransactionCaptureRefundEntity
    {
        $refund = new OrderTransactionCaptureRefundEntity();
        $refund->setId('refund-id');

        $state = new StateMachineStateEntity();
        $state->setTechnicalName(OrderTransactionCaptureRefundStates::STATE_COMPLETED);
        $refund->setStateMachineState($state);

        return $refund;
    }
}
