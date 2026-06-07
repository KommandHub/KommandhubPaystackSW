<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Service\Http;

use Kommandhub\PaystackSW\Contracts\HttpClientInterface;
use Kommandhub\PaystackSW\Exceptions\PaystackException;
use Kommandhub\PaystackSW\Service\Config;
use Symfony\Contracts\HttpClient\HttpClientInterface as SymfonyHttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Class PaystackHttpClient.
 */
class PaystackHttpClient implements HttpClientInterface
{
    private const BASE_URL = 'https://api.paystack.co';

    /**
     * PaystackHttpClient constructor.
     */
    public function __construct(
        private readonly Config $config,
        private readonly SymfonyHttpClientInterface $client,
    ) {
    }

    /**
     * Get the secret key based on configuration.
     */
    private function getSecretKey(?string $salesChannelId = null): string
    {
        $sandbox = $this->config->getBool('enableSandbox', $salesChannelId);

        return $sandbox
            ? $this->config->getString('apiSecretKeySandbox', $salesChannelId)
            : $this->config->getString('apiSecretKey', $salesChannelId);
    }

    /**
     * Send a GET request.
     *
     *
     * @throws PaystackException
     */
    public function get(string $endpoint, array $queryParams = [], ?string $salesChannelId = null): ResponseInterface
    {
        try {
            return $this->client->request('GET', self::BASE_URL . $endpoint, [
                'query' => $queryParams,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getSecretKey($salesChannelId),
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (\Throwable $e) {
            throw new PaystackException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Send a POST request.
     *
     *
     * @throws PaystackException
     */
    public function post(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface
    {
        try {
            return $this->client->request('POST', self::BASE_URL . $endpoint, [
                'json' => $payload,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getSecretKey($salesChannelId),
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (\Throwable $e) {
            throw new PaystackException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Send a PUT request.
     *
     *
     * @throws PaystackException
     */
    public function put(string $endpoint, array $payload = [], ?string $salesChannelId = null): ResponseInterface
    {
        try {
            return $this->client->request('PUT', self::BASE_URL . $endpoint, [
                'json' => $payload,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getSecretKey($salesChannelId),
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (\Throwable $e) {
            throw new PaystackException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Send a DELETE request.
     *
     *
     * @throws PaystackException
     */
    public function delete(string $endpoint, ?string $salesChannelId = null): ResponseInterface
    {
        try {
            return $this->client->request('DELETE', self::BASE_URL . $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->getSecretKey($salesChannelId),
                    'Content-Type' => 'application/json',
                ],
            ]);
        } catch (\Throwable $e) {
            throw new PaystackException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
