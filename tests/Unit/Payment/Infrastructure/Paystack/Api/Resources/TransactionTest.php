<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Payment\Infrastructure\Paystack\Api\Resources;

use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Http\HttpClientInterface;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources\Transaction;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(Transaction::class)]
class TransactionTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Transaction $transaction;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->transaction = new Transaction($this->httpClient);
    }

    public function testInitialize(): void
    {
        $payload = ['email' => 'customer@example.com', 'amount' => 10000];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/transaction/initialize', $payload)
            ->willReturn($response);

        $result = $this->transaction->initialize($payload);
        $this->assertTrue($result['status']);
    }

    public function testVerify(): void
    {
        $reference = 'REF_123';
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with("/transaction/verify/{$reference}")
            ->willReturn($response);

        $result = $this->transaction->verify($reference);
        $this->assertTrue($result['status']);
    }

    public function testList(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/transaction', [])
            ->willReturn($response);

        $result = $this->transaction->list();
        $this->assertTrue($result['status']);
    }

    public function testFetch(): void
    {
        $id = '12345';
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with("/transaction/{$id}")
            ->willReturn($response);

        $result = $this->transaction->fetch($id);
        $this->assertTrue($result['status']);
    }
}
