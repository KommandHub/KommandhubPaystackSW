<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Client\Resource;

use Kommandhub\PaystackSW\Exception\PaystackException;

/**
 * Class Split.
 */
class Split extends ApiResource
{
    /**
     * Create a split.
     *
     * @see https://paystack.com/docs/api/split/#create
     *
     * @throws PaystackException
     */
    public function create(array $payload): array
    {
        return $this->response($this->httpClient->post('/split', $payload));
    }

    /**
     * List splits.
     *
     * @see https://paystack.com/docs/api/split/#list
     *
     * @throws PaystackException
     */
    public function list(array $queryParams = []): array
    {
        return $this->response($this->httpClient->get('/split', $queryParams));
    }

    /**
     * Fetch a split.
     *
     * @see https://paystack.com/docs/api/split/#fetch
     *
     * @throws PaystackException
     */
    public function fetch(string $id): array
    {
        return $this->response($this->httpClient->get("/split/{$id}"));
    }

    /**
     * Update a split.
     *
     * @see https://paystack.com/docs/api/split/#update
     *
     * @throws PaystackException
     */
    public function update(string $id, array $payload): array
    {
        return $this->response($this->httpClient->put("/split/{$id}", $payload));
    }

    /**
     * Add a subaccount to a split.
     *
     * @see https://paystack.com/docs/api/split/#add-subaccount
     *
     * @throws PaystackException
     */
    public function addSubaccount(string $id, array $payload): array
    {
        return $this->response($this->httpClient->post("/split/{$id}/subaccount/add", $payload));
    }

    /**
     * Remove a subaccount from a split.
     *
     * @see https://paystack.com/docs/api/split/#remove-subaccount
     *
     * @throws PaystackException
     */
    public function removeSubaccount(string $id, array $payload): array
    {
        return $this->response($this->httpClient->post("/split/{$id}/subaccount/remove", $payload));
    }
}
