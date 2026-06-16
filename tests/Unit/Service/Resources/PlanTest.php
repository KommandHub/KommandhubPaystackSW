<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Service\Resources;

use Kommandhub\PaystackSW\Contracts\HttpClientInterface;
use Kommandhub\PaystackSW\Service\Resources\Plan;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(Plan::class)]
class PlanTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Plan $plan;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->plan = new Plan($this->httpClient);
    }

    public function testCreate(): void
    {
        $payload = ['name' => 'Monthly Plan', 'amount' => 500000, 'interval' => 'monthly'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/plan', $payload)
            ->willReturn($response);

        $result = $this->plan->create($payload);
        $this->assertTrue($result['status']);
    }

    public function testList(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/plan', [])
            ->willReturn($response);

        $result = $this->plan->list();
        $this->assertTrue($result['status']);
    }

    public function testFetch(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/plan/PLN_123')
            ->willReturn($response);

        $result = $this->plan->fetch('PLN_123');
        $this->assertTrue($result['status']);
    }

    public function testUpdate(): void
    {
        $payload = ['name' => 'Monthly Plan updated'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('put')
            ->with('/plan/PLN_123', $payload)
            ->willReturn($response);

        $result = $this->plan->update('PLN_123', $payload);
        $this->assertTrue($result['status']);
    }
}
