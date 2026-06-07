<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Service\Resources;

use Kommandhub\PaystackSW\Exceptions\PaystackException;

/**
 * Class Settlement
 */
class Settlement extends ApiResource
{
    /**
     * List settlements.
     *
     * @see https://paystack.com/docs/api/settlement/#list
     *
     * @throws PaystackException
     */
    public function list(array $queryParams = []): array
    {
        return $this->response($this->httpClient->get('/settlement', $queryParams));
    }

    /**
     * Fetch settlement transactions.
     *
     * @see https://paystack.com/docs/api/settlement/#transactions
     *
     * @throws PaystackException
     */
    public function transactions(string $id, array $queryParams = []): array
    {
        return $this->response($this->httpClient->get("/settlement/{$id}/transaction", $queryParams));
    }
}
