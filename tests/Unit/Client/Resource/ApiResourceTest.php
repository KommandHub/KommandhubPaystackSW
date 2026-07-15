<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Client\Resource;

use Kommandhub\PaystackSW\Client\Http\HttpClientInterface;
use Kommandhub\PaystackSW\Client\Resource\ApiResource;
use Kommandhub\PaystackSW\Client\Resource\Transaction;
use Kommandhub\PaystackSW\Exception\PaystackException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * ApiResource is abstract, so its response handling is exercised through a
 * concrete resource.
 */
#[CoversClass(ApiResource::class)]
#[UsesClass(Transaction::class)]
class ApiResourceTest extends TestCase
{
    /**
     * Paystack returns validation errors as a normal JSON body with a 4xx
     * status. The body must reach the caller so `status === false` handling and
     * the merchant-facing message survive, rather than being lost to an HTTP
     * exception.
     */
    public function testErrorBodyFromA4xxResponseIsReturnedToTheCaller(): void
    {
        $errorBody = [
            'status' => false,
            'message' => 'Invalid Email Address Passed',
            'meta' => ['nextStep' => 'Ensure you are passing the email parameter'],
        ];

        $response = $this->createMock(ResponseInterface::class);
        // toArray(false) -> does not throw on 4xx, returns the decoded body.
        $response->method('toArray')->with(false)->willReturn($errorBody);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')->willReturn($response);

        $result = (new Transaction($httpClient))->initialize(['amount' => 100]);

        static::assertSame($errorBody, $result);
        static::assertFalse($result['status']);
        static::assertSame('Invalid Email Address Passed', $result['message']);
    }

    /**
     * A transport-level failure is a real fault and must surface as a
     * PaystackException rather than a raw Symfony exception.
     */
    public function testTransportFailureIsWrappedInAPaystackException(): void
    {
        $transportException = new class('Connection timed out') extends \RuntimeException implements TransportExceptionInterface {
        };

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willThrowException($transportException);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('post')->willReturn($response);

        $this->expectException(PaystackException::class);
        $this->expectExceptionMessage('Connection timed out');

        (new Transaction($httpClient))->initialize(['amount' => 100]);
    }
}
