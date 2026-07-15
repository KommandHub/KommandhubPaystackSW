<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Client\Http;

use Kommandhub\PaystackSW\Exception\PaystackException;
use Kommandhub\PaystackSW\Setting\Service\Config;
use Kommandhub\PaystackSW\Client\Http\PaystackHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface as SymfonyHttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[CoversClass(PaystackHttpClient::class)]
class PaystackHttpClientTest extends TestCase
{
    private Config $config;
    private SymfonyHttpClientInterface $client;
    private PaystackHttpClient $paystackHttpClient;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->client = $this->createMock(SymfonyHttpClientInterface::class);
        $this->paystackHttpClient = new PaystackHttpClient($this->config, $this->client);
    }

    public function testGet(): void
    {
        $endpoint = '/test';
        $queryParams = ['foo' => 'bar'];
        $salesChannelId = 'sales-channel-id';
        $secretKey = 'sk_test_123';

        $this->config->method('getBool')->with('enableSandbox', $salesChannelId)->willReturn(true);
        $this->config->method('getString')->with('apiSecretKeySandbox', $salesChannelId)->willReturn($secretKey);

        $response = $this->createMock(ResponseInterface::class);

        $this->client->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.paystack.co' . $endpoint, [
                'query' => $queryParams,
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                    'Content-Type' => 'application/json',
                ],
            ])
            ->willReturn($response);

        $result = $this->paystackHttpClient->get($endpoint, $queryParams, $salesChannelId);
        $this->assertSame($response, $result);
    }

    public function testGetWithoutSandbox(): void
    {
        $endpoint = '/test';
        $salesChannelId = 'sales-channel-id';
        $secretKey = 'sk_live_123';

        $this->config->method('getBool')->with('enableSandbox', $salesChannelId)->willReturn(false);
        $this->config->method('getString')->with('apiSecretKey', $salesChannelId)->willReturn($secretKey);

        $response = $this->createMock(ResponseInterface::class);

        $this->client->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.paystack.co' . $endpoint, $this->callback(function ($options) use ($secretKey) {
                return $options['headers']['Authorization'] === 'Bearer ' . $secretKey;
            }))
            ->willReturn($response);

        $result = $this->paystackHttpClient->get($endpoint, [], $salesChannelId);
        $this->assertSame($response, $result);
    }

    public function testPost(): void
    {
        $endpoint = '/test';
        $payload = ['foo' => 'bar'];
        $salesChannelId = 'sales-channel-id';
        $secretKey = 'sk_live_123';

        $this->config->method('getBool')->with('enableSandbox', $salesChannelId)->willReturn(false);
        $this->config->method('getString')->with('apiSecretKey', $salesChannelId)->willReturn($secretKey);

        $response = $this->createMock(ResponseInterface::class);

        $this->client->expects($this->once())
            ->method('request')
            ->with('POST', 'https://api.paystack.co' . $endpoint, [
                'json' => $payload,
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                    'Content-Type' => 'application/json',
                ],
            ])
            ->willReturn($response);

        $result = $this->paystackHttpClient->post($endpoint, $payload, $salesChannelId);
        $this->assertSame($response, $result);
    }

    public function testPut(): void
    {
        $endpoint = '/test';
        $payload = ['foo' => 'bar'];
        $secretKey = 'sk_test_123';

        $this->config->method('getBool')->willReturn(true);
        $this->config->method('getString')->willReturn($secretKey);

        $response = $this->createMock(ResponseInterface::class);

        $this->client->expects($this->once())
            ->method('request')
            ->with('PUT', 'https://api.paystack.co' . $endpoint, [
                'json' => $payload,
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                    'Content-Type' => 'application/json',
                ],
            ])
            ->willReturn($response);

        $result = $this->paystackHttpClient->put($endpoint, $payload);
        $this->assertSame($response, $result);
    }

    public function testDelete(): void
    {
        $endpoint = '/test';
        $secretKey = 'sk_test_123';

        $this->config->method('getBool')->willReturn(true);
        $this->config->method('getString')->willReturn($secretKey);

        $response = $this->createMock(ResponseInterface::class);

        $this->client->expects($this->once())
            ->method('request')
            ->with('DELETE', 'https://api.paystack.co' . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secretKey,
                    'Content-Type' => 'application/json',
                ],
            ])
            ->willReturn($response);

        $result = $this->paystackHttpClient->delete($endpoint);
        $this->assertSame($response, $result);
    }

    public function testRequestThrowsPaystackException(): void
    {
        $this->config->method('getBool')->willReturn(true);
        $this->config->method('getString')->willReturn('key');

        $this->client->method('request')->willThrowException(new \Exception('error', 500));

        $this->expectException(PaystackException::class);
        $this->expectExceptionMessage('error');
        $this->expectExceptionCode(500);

        $this->paystackHttpClient->get('/test');
    }

    public function testPostThrowsPaystackException(): void
    {
        $this->config->method('getBool')->willReturn(true);
        $this->config->method('getString')->willReturn('key');
        $this->client->method('request')->willThrowException(new \Exception('post error', 400));
        $this->expectException(PaystackException::class);
        $this->expectExceptionMessage('post error');
        $this->paystackHttpClient->post('/test', []);
    }

    public function testPutThrowsPaystackException(): void
    {
        $this->config->method('getBool')->willReturn(true);
        $this->config->method('getString')->willReturn('key');
        $this->client->method('request')->willThrowException(new \Exception('put error', 400));
        $this->expectException(PaystackException::class);
        $this->expectExceptionMessage('put error');
        $this->paystackHttpClient->put('/test', []);
    }

    public function testDeleteThrowsPaystackException(): void
    {
        $this->config->method('getBool')->willReturn(true);
        $this->config->method('getString')->willReturn('key');
        $this->client->method('request')->willThrowException(new \Exception('delete error', 400));
        $this->expectException(PaystackException::class);
        $this->expectExceptionMessage('delete error');
        $this->paystackHttpClient->delete('/test');
    }
}
