<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Service;

use Kommandhub\PaystackSW\Service\Paystack;
use Kommandhub\PaystackSW\Service\Resources\Transaction;
use Kommandhub\PaystackSW\Service\TransactionService;
use PHPUnit\Framework\TestCase;

class TransactionServiceTest extends TestCase
{
    private Paystack $paystack;
    private TransactionService $transactionService;
    private Transaction $transactionResource;

    protected function setUp(): void
    {
        $this->paystack = $this->createMock(Paystack::class);
        $this->transactionResource = $this->createMock(Transaction::class);

        $this->paystack->method('transactions')->willReturn($this->transactionResource);

        $this->transactionService = new TransactionService($this->paystack);
    }

    public function testInitialize(): void
    {
        $payload = ['amount' => 10000];
        $expectedResponse = ['status' => true, 'data' => ['authorization_url' => 'https://paystack.com/checkout']];

        $this->transactionResource->expects($this->once())
            ->method('initialize')
            ->with($payload)
            ->willReturn($expectedResponse);

        $response = $this->transactionService->initialize($payload);

        $this->assertEquals($expectedResponse, $response);
    }

    public function testVerify(): void
    {
        $reference = 'test_reference';
        $expectedResponse = ['status' => true, 'data' => ['status' => 'success']];

        $this->transactionResource->expects($this->once())
            ->method('verify')
            ->with($reference)
            ->willReturn($expectedResponse);

        $response = $this->transactionService->verify($reference);

        $this->assertEquals($expectedResponse, $response);
    }
}
