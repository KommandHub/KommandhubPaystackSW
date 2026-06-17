<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Http;

use Kommandhub\PaystackSW\Core\Exception\PaystackException;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Interface HttpClientInterface.
 */
interface HttpClientInterface
{
    /**
     * Send a GET request.
     *
     *
     * @throws PaystackException
     */
    public function get(string $endpoint, array $queryParams = [], ?string $salesChannelId = null): ResponseInterface;

    /**
     * Send a POST request.
     *
     *
     * @throws PaystackException
     */
    public function post(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface;

    /**
     * Send a PUT request.
     *
     *
     * @throws PaystackException
     */
    public function put(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface;

    /**
     * Send a DELETE request.
     *
     *
     * @throws PaystackException
     */
    public function delete(string $endpoint, ?string $salesChannelId = null): ResponseInterface;
}
