<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Service\Resources;

use Kommandhub\PaystackSW\Exceptions\PaystackException;

/**
 * Class Transaction
 */
class Transaction extends ApiResource
{
    /**
     * Initialize a transaction.
     *
     * @see https://paystack.com/docs/api/transaction/#initialize
     *
     * @throws PaystackException
     */
    public function initialize(array $payload): array
    {
        return $this->response($this->httpClient->post('/transaction/initialize', $payload));
    }

    /**
     * Verify a transaction.
     *
     * @see https://paystack.com/docs/api/transaction/#verify
     *
     * @throws PaystackException
     */
    public function verify(string $reference): array
    {
        return $this->response($this->httpClient->get("/transaction/verify/{$reference}"));
    }

    /**
     * List transactions.
     *
     * @see https://paystack.com/docs/api/transaction/#list
     *
     * @throws PaystackException
     */
    public function list(array $queryParams = []): array
    {
        return $this->response($this->httpClient->get('/transaction', $queryParams));
    }

    /**
     * Fetch a transaction.
     *
     * @see https://paystack.com/docs/api/transaction/#fetch
     *
     * @throws PaystackException
     */
    public function fetch(string $id): array
    {
        return $this->response($this->httpClient->get("/transaction/{$id}"));
    }
}
