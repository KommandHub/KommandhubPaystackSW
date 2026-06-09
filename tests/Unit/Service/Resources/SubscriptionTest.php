<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Service\Resources;

use Kommandhub\PaystackSW\Contracts\HttpClientInterface;
use Kommandhub\PaystackSW\Service\Resources\Subscription;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

class SubscriptionTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Subscription $subscription;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->subscription = new Subscription($this->httpClient);
    }

    public function testCreate(): void
    {
        $payload = ['customer' => 'CUS_123', 'plan' => 'PLN_123'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/subscription', $payload)
            ->willReturn($response);

        $result = $this->subscription->create($payload);
        $this->assertTrue($result['status']);
    }

    public function testList(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/subscription', [])
            ->willReturn($response);

        $result = $this->subscription->list();
        $this->assertTrue($result['status']);
    }

    public function testFetch(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/subscription/SUB_123')
            ->willReturn($response);

        $result = $this->subscription->fetch('SUB_123');
        $this->assertTrue($result['status']);
    }

    public function testEnable(): void
    {
        $payload = ['code' => 'SUB_123', 'token' => 'TOK_123'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/subscription/enable', $payload)
            ->willReturn($response);

        $result = $this->subscription->enable($payload);
        $this->assertTrue($result['status']);
    }

    public function testDisable(): void
    {
        $payload = ['code' => 'SUB_123', 'token' => 'TOK_123'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/subscription/disable', $payload)
            ->willReturn($response);

        $result = $this->subscription->disable($payload);
        $this->assertTrue($result['status']);
    }

    public function testManageLink(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/subscription/SUB_123/manage/link')
            ->willReturn($response);

        $result = $this->subscription->manageLink('SUB_123');
        $this->assertTrue($result['status']);
    }

    public function testSendManageLink(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/subscription/SUB_123/manage/email')
            ->willReturn($response);

        $result = $this->subscription->sendManageLink('SUB_123');
        $this->assertTrue($result['status']);
    }
}
