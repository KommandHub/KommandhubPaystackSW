<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Client\Resource;

use Kommandhub\PaystackSW\Exception\PaystackException;

/**
 * Class Miscellaneous.
 */
class Miscellaneous extends ApiResource
{
    /**
     * Get a list of all supported banks and their properties.
     *
     * @see https://paystack.com/docs/api/miscellaneous/#bank
     *
     * @throws PaystackException
     */
    public function listBanks(array $queryParams = []): array
    {
        return $this->response($this->httpClient->get('/bank', $queryParams));
    }

    /**
     * Get a list of all supported countries and their properties.
     *
     * @see https://paystack.com/docs/api/miscellaneous/#country
     *
     * @throws PaystackException
     */
    public function listCountries(): array
    {
        return $this->response($this->httpClient->get('/country'));
    }

    /**
     * Get a list of states for a specific country for Address Verification Service (AVS).
     *
     * @see https://paystack.com/docs/api/miscellaneous/#avs-states
     *
     * @throws PaystackException
     */
    public function listStates(string $countryCode): array
    {
        return $this->response($this->httpClient->get('/address_verification/states', ['country' => $countryCode]));
    }
}
