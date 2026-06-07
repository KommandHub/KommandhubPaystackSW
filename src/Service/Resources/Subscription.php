<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Service\Resources;

use Kommandhub\PaystackSW\Exceptions\PaystackException;
use Kommandhub\PaystackSW\Service\Resources\ApiResource;

/**
 * Class Subscription
 */
class Subscription extends ApiResource
{
    /**
     * Create a subscription.
     *
     * @see https://paystack.com/docs/api/subscription/#create
     *
     * @throws PaystackException
     */
    public function create(array $payload): array
    {
        return $this->response($this->httpClient->post('/subscription', $payload));
    }

    /**
     * List subscriptions.
     *
     * @see https://paystack.com/docs/api/subscription/#list
     *
     * @throws PaystackException
     */
    public function list(array $queryParams = []): array
    {
        return $this->response($this->httpClient->get('/subscription', $queryParams));
    }

    /**
     * Fetch a subscription.
     *
     * @see https://paystack.com/docs/api/subscription/#fetch
     *
     * @throws PaystackException
     */
    public function fetch(string $idOrCode): array
    {
        return $this->response($this->httpClient->get("/subscription/{$idOrCode}"));
    }

    /**
     * Enable a subscription.
     *
     * @see https://paystack.com/docs/api/subscription/#enable
     *
     * @throws PaystackException
     */
    public function enable(array $payload): array
    {
        return $this->response($this->httpClient->post('/subscription/enable', $payload));
    }

    /**
     * Disable a subscription.
     *
     * @see https://paystack.com/docs/api/subscription/#disable
     *
     * @throws PaystackException
     */
    public function disable(array $payload): array
    {
        return $this->response($this->httpClient->post('/subscription/disable', $payload));
    }

    /**
     * Generate a link for managing a subscription.
     *
     * @see https://paystack.com/docs/api/subscription/#manage-link
     *
     * @throws PaystackException
     */
    public function manageLink(string $code): array
    {
        return $this->response($this->httpClient->get("/subscription/{$code}/manage/link"));
    }

    /**
     * Send a link for managing a subscription.
     *
     * @see https://paystack.com/docs/api/subscription/#manage-email
     *
     * @throws PaystackException
     */
    public function sendManageLink(string $code): array
    {
        return $this->response($this->httpClient->post("/subscription/{$code}/manage/email"));
    }
}
