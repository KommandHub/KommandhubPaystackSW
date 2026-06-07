<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Service\Resources;

use Kommandhub\PaystackSW\Exceptions\PaystackException;
use Kommandhub\PaystackSW\Service\Resources\ApiResource;

/**
 * Class Customer
 */
class Customer extends ApiResource
{
    /**
     * Create a customer.
     *
     * @see https://paystack.com/docs/api/customer/#create
     *
     * @throws PaystackException
     */
    public function create(array $payload): array
    {
        return $this->response($this->httpClient->post('/customer', $payload));
    }

    /**
     * List customers.
     *
     * @see https://paystack.com/docs/api/customer/#list
     *
     * @throws PaystackException
     */
    public function list(array $queryParams = []): array
    {
        return $this->response($this->httpClient->get('/customer', $queryParams));
    }

    /**
     * Fetch a customer.
     *
     * @see https://paystack.com/docs/api/customer/#fetch
     *
     * @throws PaystackException
     */
    public function fetch(string $emailOrCode): array
    {
        return $this->response($this->httpClient->get("/customer/{$emailOrCode}"));
    }

    /**
     * Update a customer.
     *
     * @see https://paystack.com/docs/api/customer/#update
     *
     * @throws PaystackException
     */
    public function update(string $code, array $payload): array
    {
        return $this->response($this->httpClient->put("/customer/{$code}", $payload));
    }
}
