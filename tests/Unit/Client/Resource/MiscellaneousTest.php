<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Client\Resource;

use Kommandhub\PaystackSW\Client\Http\HttpClientInterface;
use Kommandhub\PaystackSW\Client\Resource\Miscellaneous;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(Miscellaneous::class)]
class MiscellaneousTest extends TestCase
{
    private HttpClientInterface $httpClient;
    private Miscellaneous $miscellaneous;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(HttpClientInterface::class);
        $this->miscellaneous = new Miscellaneous($this->httpClient);
    }

    public function testListBanks(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/bank', [])
            ->willReturn($response);

        $result = $this->miscellaneous->listBanks();
        $this->assertTrue($result['status']);
    }

    public function testListCountries(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/country')
            ->willReturn($response);

        $result = $this->miscellaneous->listCountries();
        $this->assertTrue($result['status']);
    }

    public function testListStates(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['status' => true]);

        $this->httpClient->expects($this->once())
            ->method('get')
            ->with('/address_verification/states', ['country' => 'NG'])
            ->willReturn($response);

        $result = $this->miscellaneous->listStates('NG');
        $this->assertTrue($result['status']);
    }
}
