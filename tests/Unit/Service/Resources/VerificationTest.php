<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Service\Resources;

use Kommandhub\PaystackSW\Contracts\HttpClientInterface;
use Kommandhub\PaystackSW\Service\Resources\Verification;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

class VerificationTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Verification $verification;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->verification = new Verification($this->httpClient);
    }

    public function testResolveAccount(): void
    {
        $accountNumber = '0001234567';
        $bankCode = '058';
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true, 'data' => []]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/bank/resolve', [
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
            ])
            ->willReturn($response);

        $result = $this->verification->resolveAccount($accountNumber, $bankCode);
        $this->assertTrue($result['status']);
    }

    public function testResolveCardBin(): void
    {
        $bin = '412345';
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true, 'data' => []]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with("/decision/bin/{$bin}")
            ->willReturn($response);

        $result = $this->verification->resolveCardBin($bin);
        $this->assertTrue($result['status']);
    }
}
