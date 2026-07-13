<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Client\Resource;

use Kommandhub\PaystackSW\Client\Http\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class ApiResource
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
     */
    protected function response(ResponseInterface $response): array
    {
        return $response->toArray();
    }
}
