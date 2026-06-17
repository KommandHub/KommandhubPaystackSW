<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources;

use Kommandhub\PaystackSW\Core\Exception\PaystackException;
use Kommandhub\PaystackSW\Payment\Infrastructure\Paystack\Api\Resources\ApiResource;

/**
 * Class Verification
 */
class Verification extends ApiResource
{
    /**
     * Resolve account number.
     *
     * @see https://paystack.com/docs/api/verification/#resolve-account
     *
     * @throws PaystackException
     */
    public function resolveAccount(string $accountNumber, string $bankCode): array
    {
        return $this->response($this->httpClient->get('/bank/resolve', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
        ]));
    }

    /**
     * Resolve card BIN.
     *
     * @see https://paystack.com/docs/api/verification/#resolve-card-bin
     *
     * @throws PaystackException
     */
    public function resolveCardBin(string $bin): array
    {
        return $this->response($this->httpClient->get("/decision/bin/{$bin}"));
    }
}
