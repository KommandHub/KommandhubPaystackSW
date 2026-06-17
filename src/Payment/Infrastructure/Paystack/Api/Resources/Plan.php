<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources;

use Kommandhub\PaystackSW\Core\Exception\PaystackException;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources\ApiResource;

/**
 * Class Plan
 */
class Plan extends ApiResource
{
    /**
     * Create a plan.
     *
     * @see https://paystack.com/docs/api/plan/#create
     *
     * @throws PaystackException
     */
    public function create(array $payload): array
    {
        return $this->response($this->httpClient->post('/plan', $payload));
    }

    /**
     * List plans.
     *
     * @see https://paystack.com/docs/api/plan/#list
     *
     * @throws PaystackException
     */
    public function list(array $queryParams = []): array
    {
        return $this->response($this->httpClient->get('/plan', $queryParams));
    }

    /**
     * Fetch a plan.
     *
     * @see https://paystack.com/docs/api/plan/#fetch
     *
     * @throws PaystackException
     */
    public function fetch(string $idOrCode): array
    {
        return $this->response($this->httpClient->get("/plan/{$idOrCode}"));
    }

    /**
     * Update a plan.
     *
     * @see https://paystack.com/docs/api/plan/#update
     *
     * @throws PaystackException
     */
    public function update(string $idOrCode, array $payload): array
    {
        return $this->response($this->httpClient->put("/plan/{$idOrCode}", $payload));
    }
}
