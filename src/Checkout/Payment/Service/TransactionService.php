<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Service;

use Kommandhub\PaystackSW\Exception\PaystackException;
use Kommandhub\PaystackSW\Client\PaystackClient;

class TransactionService
{
    public function __construct(
        private readonly PaystackClient $paystack
    ) {
    }

    /**
     * @param array $payload The transaction initialization data.
     *
     * @return array The response from Paystack's initialization API.
     *
     * @throws PaystackException If the API request fails.
     */
    public function initialize(array $payload): array
    {
        // Delegates the initialization to the Paystack client's transaction resource.
        return $this->paystack->transactions()->initialize($payload);
    }

    /**
     * @param string $reference The unique transaction reference from Paystack.
     *
     * @return array The response from Paystack's verification API.
     *
     * @throws PaystackException If the API request fails.
     */
    public function verify(string $reference): array
    {
        // Delegates the verification to the Paystack client's transaction resource.
        return $this->paystack->transactions()->verify($reference);
    }
}
