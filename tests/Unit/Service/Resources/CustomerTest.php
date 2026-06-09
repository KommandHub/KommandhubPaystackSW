<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Service\Resources;

use Kommandhub\PaystackSW\Contracts\HttpClientInterface;
use Kommandhub\PaystackSW\Service\Resources\Customer;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

class CustomerTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Customer $customer;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->customer = new Customer($this->httpClient);
    }

    public function testCreate(): void
    {
        $payload = ['email' => 'customer@example.com'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('post')
            ->with('/customer', $payload)
            ->willReturn($response);

        $result = $this->customer->create($payload);
        $this->assertTrue($result['status']);
    }

    public function testList(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/customer', [])
            ->willReturn($response);

        $result = $this->customer->list();
        $this->assertTrue($result['status']);
    }

    public function testFetch(): void
    {
        $emailOrCode = 'CUS_123';
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with("/customer/{$emailOrCode}")
            ->willReturn($response);

        $result = $this->customer->fetch($emailOrCode);
        $this->assertTrue($result['status']);
    }

    public function testUpdate(): void
    {
        $code = 'CUS_123';
        $payload = ['first_name' => 'John'];
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('put')
            ->with("/customer/{$code}", $payload)
            ->willReturn($response);

        $result = $this->customer->update($code, $payload);
        $this->assertTrue($result['status']);
    }
}
