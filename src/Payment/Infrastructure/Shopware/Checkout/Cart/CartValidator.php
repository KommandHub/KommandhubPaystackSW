<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\Checkout\Cart;

use Kommandhub\PaystackSW\Core\Config\Config;
use Kommandhub\PaystackSW\Core\Logging\ConfigurableLogger;
use Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\Checkout\Cart\Error\ConfigurationError;
use Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\Handler\PaystackPaymentHandler;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Validates Paystack-specific checkout requirements.
 *
 * This validator ensures that the required Paystack API credentials are
 * configured before a customer can place an order using the Paystack
 * payment method.
 */
#[AutoconfigureTag('shopware.cart.validator')]
readonly class CartValidator implements CartValidatorInterface
{
    public function __construct(
        private Config $config,
        private ConfigurableLogger $logger
    ) {
    }

    /**
     * Validates the cart for Paystack-specific configuration issues.
     */
    public function validate(
        Cart $cart,
        ErrorCollection $errors,
        SalesChannelContext $context
    ): void {
        if (!$this->isPaystackPaymentSelected($context)) {
            return;
        }

        if ($this->hasConfigurationError($cart)) {
            return;
        }

        $salesChannelId = $context->getSalesChannelId();
        $isSandbox = $this->config->getBool('enableSandbox', $salesChannelId);

        if ($this->hasSecretKeyConfigured($salesChannelId, $isSandbox)) {
            return;
        }

        $this->logger->error('Paystack API secret key is not configured.', [
            'salesChannelId' => $salesChannelId,
            'isSandbox' => $isSandbox,
            'cartToken' => $cart->getToken(),
        ]);

        $errors->add(new ConfigurationError());
    }

    /**
     * Determines whether Paystack is the currently selected payment method.
     */
    private function isPaystackPaymentSelected(
        SalesChannelContext $context
    ): bool {
        return $context->getPaymentMethod()->getHandlerIdentifier()
            === PaystackPaymentHandler::class;
    }

    /**
     * Determines whether the cart already contains a Paystack
     * configuration error.
     */
    private function hasConfigurationError(Cart $cart): bool
    {
        foreach ($cart->getErrors() as $error) {
            if ($error instanceof ConfigurationError) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines whether the required Paystack secret key is configured.
     */
    private function hasSecretKeyConfigured(
        string $salesChannelId,
        bool $isSandbox
    ): bool {
        $secretKey = $isSandbox
            ? $this->config->getString('apiSecretKeySandbox', $salesChannelId)
            : $this->config->getString('apiSecretKey', $salesChannelId);

        return $secretKey !== '';
    }
}