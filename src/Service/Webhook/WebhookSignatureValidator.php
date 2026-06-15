<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Service\Webhook;

use Kommandhub\PaystackSW\Service\Config;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class WebhookSignatureValidator
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Validates the Paystack webhook signature.
     *
     * @throws AccessDeniedHttpException If validation fails.
     */
    public function validate(Request $request): void
    {
        $signature = $request->headers->get('x-paystack-signature');
        if (!$signature) {
            throw new AccessDeniedHttpException('Missing Paystack signature header.');
        }

        $isSandbox = $this->config->getBool('enableSandbox');
        $secret = $isSandbox
            ? $this->config->getString('apiSecretKeySandbox')
            : $this->config->getString('apiSecretKey');

        if (!$secret) {
            throw new AccessDeniedHttpException('Paystack API secret key not configured.');
        }

        $payload = $request->getContent();
        $expectedSignature = hash_hmac('sha512', $payload, $secret);

        if (!hash_equals($expectedSignature, $signature)) {
            throw new AccessDeniedHttpException('Invalid Paystack signature.');
        }
    }
}
