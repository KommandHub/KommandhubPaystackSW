<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources;

use Kommandhub\PaystackSW\Core\Exception\PaystackException;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources\ApiResource;

/**
 * Class Refund
 */
class Refund extends ApiResource
{
    /**
     * Create a refund.
     *
     * @see https://paystack.com/docs/api/refund/#create
     *
     * @throws PaystackException
     */
    public function create(array $payload): array
    {
        return $this->response($this->httpClient->post('/refund', $payload));
    }

    /**
     * List refunds.
     *
     * @see https://paystack.com/docs/api/refund/#list
     *
     * @throws PaystackException
     */
    public function list(array $queryParams = []): array
    {
        return $this->response($this->httpClient->get('/refund', $queryParams));
    }

    /**
     * Fetch a refund.
     *
     * @see https://paystack.com/docs/api/refund/#fetch
     *
     * @throws PaystackException
     */
    public function fetch(string $reference): array
    {
        return $this->response($this->httpClient->get("/refund/{$reference}"));
    }
}
