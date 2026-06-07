<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Service\Resources;

use Kommandhub\PaystackSW\Exceptions\PaystackException;

/**
 * Class Subaccount
 */
class Subaccount extends ApiResource
{
    /**
     * Create a subaccount.
     *
     * @see https://paystack.com/docs/api/subaccount/#create
     *
     * @throws PaystackException
     */
    public function create(array $payload): array
    {
        return $this->response($this->httpClient->post('/subaccount', $payload));
    }

    /**
     * List subaccounts.
     *
     * @see https://paystack.com/docs/api/subaccount/#list
     *
     * @throws PaystackException
     */
    public function list(array $queryParams = []): array
    {
        return $this->response($this->httpClient->get('/subaccount', $queryParams));
    }

    /**
     * Fetch a subaccount.
     *
     * @see https://paystack.com/docs/api/subaccount/#fetch
     *
     * @throws PaystackException
     */
    public function fetch(string $idOrCode): array
    {
        return $this->response($this->httpClient->get("/subaccount/{$idOrCode}"));
    }

    /**
     * Update a subaccount.
     *
     * @see https://paystack.com/docs/api/subaccount/#update
     *
     * @throws PaystackException
     */
    public function update(string $idOrCode, array $payload): array
    {
        return $this->response($this->httpClient->put("/subaccount/{$idOrCode}", $payload));
    }
}
