<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Client;

use Kommandhub\PaystackSW\Client\Http\HttpClientInterface;
use Kommandhub\PaystackSW\Client\Resource\Customer;
use Kommandhub\PaystackSW\Client\Resource\Miscellaneous;
use Kommandhub\PaystackSW\Client\Resource\Plan;
use Kommandhub\PaystackSW\Client\Resource\Refund;
use Kommandhub\PaystackSW\Client\Resource\Split;
use Kommandhub\PaystackSW\Client\Resource\Subscription;
use Kommandhub\PaystackSW\Client\Resource\Transaction;
use Kommandhub\PaystackSW\Client\Resource\Transfer;
use Kommandhub\PaystackSW\Client\Resource\Verification;
use Kommandhub\PaystackSW\Client\Resource\Settlement;
use Kommandhub\PaystackSW\Client\Resource\Subaccount;

/**
 * Class PaystackClient.
 */
class PaystackClient
{
    /**
     * PaystackClient constructor.
     */
    public function __construct(
        private HttpClientInterface $httpClient
    ) {
    }

    /**
     * Get the transaction resource.
     */
    public function transactions(): Transaction
    {
        return new Transaction($this->httpClient);
    }

    /**
     * Get the customer's resource.
     */
    public function customers(): Customer
    {
        return new Customer($this->httpClient);
    }

    /**
     * Get the plan resource.
     */
    public function plans(): Plan
    {
        return new Plan($this->httpClient);
    }

    /**
     * Get the split resource.
     */
    public function splits(): Split
    {
        return new Split($this->httpClient);
    }

    /**
     * Get the subaccounts resource.
     */
    public function subaccounts(): Subaccount
    {
        return new Subaccount($this->httpClient);
    }

    /**
     * Get the subscription resource.
     */
    public function subscriptions(): Subscription
    {
        return new Subscription($this->httpClient);
    }

    /**
     * Get the refund resource.
     */
    public function refunds(): Refund
    {
        return new Refund($this->httpClient);
    }

    /**
     * Get the miscellaneous resource.
     */
    public function miscellaneous(): Miscellaneous
    {
        return new Miscellaneous($this->httpClient);
    }

    /**
     * Get the transfer resource.
     */
    public function transfers(): Transfer
    {
        return new Transfer($this->httpClient);
    }

    /**
     * Get the settlement resource.
     */
    public function settlements(): Settlement
    {
        return new Settlement($this->httpClient);
    }

    /**
     * Get the verification resource.
     */
    public function verification(): Verification
    {
        return new Verification($this->httpClient);
    }
}
