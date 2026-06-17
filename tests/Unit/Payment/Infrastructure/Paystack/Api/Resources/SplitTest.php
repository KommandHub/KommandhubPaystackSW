<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Payment\Infrastructure\Paystack\Api\Resources;

use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Http\HttpClientInterface;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources\Split;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(Split::class)]
class SplitTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Split $split;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->split = new Split($this->httpClient);
    }

    public function testCreate(): void
    {
        $payload = ['name' => 'Split', 'type' => 'percentage', 'currency' => 'NGN'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/split', $payload)
            ->willReturn($response);

        $result = $this->split->create($payload);
        $this->assertTrue($result['status']);
    }

    public function testList(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/split', [])
            ->willReturn($response);

        $result = $this->split->list();
        $this->assertTrue($result['status']);
    }

    public function testFetch(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/split/SPL_123')
            ->willReturn($response);

        $result = $this->split->fetch('SPL_123');
        $this->assertTrue($result['status']);
    }

    public function testUpdate(): void
    {
        $payload = ['name' => 'Updated Split Name'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('put')
            ->with('/split/SPL_123', $payload)
            ->willReturn($response);

        $result = $this->split->update('SPL_123', $payload);
        $this->assertTrue($result['status']);
    }

    public function testAddSubaccount(): void
    {
        $payload = ['subaccount' => 'SUB_123', 'share' => 20];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/split/SPL_123/subaccount/add', $payload)
            ->willReturn($response);

        $result = $this->split->addSubaccount('SPL_123', $payload);
        $this->assertTrue($result['status']);
    }

    public function testRemoveSubaccount(): void
    {
        $payload = ['subaccount' => 'SUB_123'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/split/SPL_123/subaccount/remove', $payload)
            ->willReturn($response);

        $result = $this->split->removeSubaccount('SPL_123', $payload);
        $this->assertTrue($result['status']);
    }
}
