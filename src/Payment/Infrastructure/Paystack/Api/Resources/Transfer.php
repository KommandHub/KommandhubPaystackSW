<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources;

use Kommandhub\PaystackSW\Core\Exception\PaystackException;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources\ApiResource;

/**
 * Class Transfer
 */
class Transfer extends ApiResource
{
    /**
     * Create a transfer recipient.
     *
     * @see https://paystack.com/docs/api/transfer-recipient/#create
     *
     * @throws PaystackException
     */
    public function recipient(array $payload): array
    {
        return $this->response($this->httpClient->post('/transferrecipient', $payload));
    }

    /**
     * Initiate a transfer.
     *
     * @see https://paystack.com/docs/api/transfer/#initiate
     *
     * @throws PaystackException
     */
    public function initiate(array $payload): array
    {
        return $this->response($this->httpClient->post('/transfer', $payload));
    }

    /**
     * List transfers.
     *
     * @see https://paystack.com/docs/api/transfer/#list
     *
     * @throws PaystackException
     */
    public function list(array $queryParams = []): array
    {
        return $this->response($this->httpClient->get('/transfer', $queryParams));
    }

    /**
     * Fetch a transfer.
     *
     * @see https://paystack.com/docs/api/transfer/#fetch
     *
     * @throws PaystackException
     */
    public function fetch(string $idOrCode): array
    {
        return $this->response($this->httpClient->get("/transfer/{$idOrCode}"));
    }

    /**
     * Verify a transfer.
     *
     * @see https://paystack.com/docs/api/transfer/#verify
     *
     * @throws PaystackException
     */
    public function verify(string $reference): array
    {
        return $this->response($this->httpClient->get("/transfer/verify/{$reference}"));
    }
}
