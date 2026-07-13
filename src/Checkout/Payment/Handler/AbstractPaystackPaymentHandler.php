<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Handler;

use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler as ShopwareAbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Framework\Context;

/**
 * Abstract payment handler for Paystack Shopware integration.
 *
 * This class extends the Shopware abstract payment handler and provides
 * a base implementation for payment handlers in the plugin.
 */
abstract class AbstractPaystackPaymentHandler extends ShopwareAbstractPaymentHandler
{
    /**
     * Determines if the payment handler supports the given payment type.
     *
     * @param PaymentHandlerType $type
     * @param string $paymentMethodId
     * @param Context $context
     *
     * @return bool Always returns false in the base implementation.
     */
    public function supports(PaymentHandlerType $type, string $paymentMethodId, Context $context): bool
    {
        if ($type === PaymentHandlerType::REFUND) {
            return true;
        }

        return false;
    }
}
