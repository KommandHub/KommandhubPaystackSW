<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Client\Resource;

use Kommandhub\PaystackSW\Client\Http\HttpClientInterface;
use Kommandhub\PaystackSW\Client\Resource\Refund;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(Refund::class)]
class RefundTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Refund $refund;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->refund = new Refund($this->httpClient);
    }

    public function testCreate(): void
    {
        $payload = ['transaction' => 'REF_123', 'amount' => 5000];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/refund', $payload)
            ->willReturn($response);

        $result = $this->refund->create($payload);
        $this->assertTrue($result['status']);
    }

    public function testList(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/refund', [])
            ->willReturn($response);

        $result = $this->refund->list();
        $this->assertTrue($result['status']);
    }

    public function testFetch(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/refund/REF_123')
            ->willReturn($response);

        $result = $this->refund->fetch('REF_123');
        $this->assertTrue($result['status']);
    }
}
