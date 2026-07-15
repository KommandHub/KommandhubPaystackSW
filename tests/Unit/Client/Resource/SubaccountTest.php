<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Client\Resource;

use Kommandhub\PaystackSW\Client\Http\HttpClientInterface;
use Kommandhub\PaystackSW\Client\Resource\Subaccount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(Subaccount::class)]
class SubaccountTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Subaccount $subaccount;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->subaccount = new Subaccount($this->httpClient);
    }

    public function testCreate(): void
    {
        $payload = ['business_name' => 'Sub', 'settlement_bank' => '058', 'account_number' => '0123456789'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/subaccount', $payload)
            ->willReturn($response);

        $result = $this->subaccount->create($payload);
        $this->assertTrue($result['status']);
    }

    public function testList(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/subaccount', [])
            ->willReturn($response);

        $result = $this->subaccount->list();
        $this->assertTrue($result['status']);
    }

    public function testFetch(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/subaccount/SUB_123')
            ->willReturn($response);

        $result = $this->subaccount->fetch('SUB_123');
        $this->assertTrue($result['status']);
    }

    public function testUpdate(): void
    {
        $payload = ['business_name' => 'Updated Business Name'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('put')
            ->with('/subaccount/SUB_123', $payload)
            ->willReturn($response);

        $result = $this->subaccount->update('SUB_123', $payload);
        $this->assertTrue($result['status']);
    }
}
