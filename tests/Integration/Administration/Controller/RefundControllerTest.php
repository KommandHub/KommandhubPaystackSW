<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Integration\Administration\Controller;

use Kommandhub\PaystackSW\Administration\Controller\RefundController;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Paystack;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
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
    private RefundController $controller;

    protected function setUp(): void
    {
        $this->paystack = $this->createMock(Paystack::class);
        $this->refundResource = $this->createMock(Refund::class);

        $this->paystack->method('refunds')->willReturn($this->refundResource);

        $this->controller = new RefundController($this->paystack);
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

        $response = $this->controller->refund($request);

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

        $response = $this->controller->refund($request);

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

        $this->refundResource->method('create')
            ->willThrowException(new \Exception('Paystack error'));

        $response = $this->controller->refund($request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('Paystack error', $responseData['error']);
    }
}
