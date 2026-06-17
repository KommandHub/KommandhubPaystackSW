<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Payment\Infrastructure\Paystack\Api\Resources;

use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Http\HttpClientInterface;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources\Settlement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(Settlement::class)]
class SettlementTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Settlement $settlement;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->settlement = new Settlement($this->httpClient);
    }

    public function testList(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/settlement', [])
            ->willReturn($response);

        $result = $this->settlement->list();
        $this->assertTrue($result['status']);
    }

    public function testTransactions(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/settlement/SET_123/transaction', [])
            ->willReturn($response);

        $result = $this->settlement->transactions('SET_123');
        $this->assertTrue($result['status']);
    }
}
