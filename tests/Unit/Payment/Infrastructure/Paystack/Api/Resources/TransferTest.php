<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Payment\Infrastructure\Paystack\Api\Resources;

use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Http\HttpClientInterface;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources\Transfer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(Transfer::class)]
class TransferTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Transfer $transfer;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->transfer = new Transfer($this->httpClient);
    }

    public function testRecipient(): void
    {
        $payload = ['type' => 'nuban', 'name' => 'John Doe'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true, 'data' => []]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/transferrecipient', $payload)
            ->willReturn($response);

        $result = $this->transfer->recipient($payload);
        $this->assertTrue($result['status']);
    }

    public function testInitiate(): void
    {
        $payload = ['source' => 'balance', 'amount' => 1000];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true, 'data' => []]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/transfer', $payload)
            ->willReturn($response);

        $result = $this->transfer->initiate($payload);
        $this->assertTrue($result['status']);
    }

    public function testList(): void
    {
        $queryParams = ['perPage' => 10];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true, 'data' => []]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/transfer', $queryParams)
            ->willReturn($response);

        $result = $this->transfer->list($queryParams);
        $this->assertTrue($result['status']);
    }

    public function testFetch(): void
    {
        $idOrCode = 'TRF_123';
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true, 'data' => []]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with("/transfer/{$idOrCode}")
            ->willReturn($response);

        $result = $this->transfer->fetch($idOrCode);
        $this->assertTrue($result['status']);
    }

    public function testVerify(): void
    {
        $reference = 'REF_123';
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true, 'data' => []]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with("/transfer/verify/{$reference}")
            ->willReturn($response);

        $result = $this->transfer->verify($reference);
        $this->assertTrue($result['status']);
    }
}
