<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Client\Resource;

use Kommandhub\PaystackSW\Client\Http\HttpClientInterface;
use Kommandhub\PaystackSW\Exception\PaystackException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class ApiResource.
 */
abstract class ApiResource
{
    /**
     * ApiResource constructor.
     */
    public function __construct(protected HttpClientInterface $httpClient)
    {
    }

    /**
     * Parse the response from the API.
     *
     * Paystack answers errors with a normal JSON body — `{"status": false,
     * "message": "...", "meta": {"nextStep": "..."}}` — alongside a 4xx status.
     * `toArray()` throws on non-2xx by default, which discarded that body and
     * left callers with an opaque HTTP exception: every `status === false`
     * branch in this plugin was unreachable and Paystack's actionable message
     * never reached the merchant. Pass `false` so the body always survives and
     * callers can act on it.
     *
     * Transport failures (DNS, timeout, TLS) and non-JSON bodies are real
     * faults and are surfaced as PaystackException.
     *
     * @return array<string, mixed>
     *
     * @throws PaystackException
     */
    protected function response(ResponseInterface $response): array
    {
        try {
            return $response->toArray(false);
        } catch (\Throwable $exception) {
            throw new PaystackException($exception->getMessage(), (int)$exception->getCode(), $exception);
        }
    }
}
