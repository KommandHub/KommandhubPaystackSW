<?php

namespace Kommandhub\PaystackSW\Exceptions;

use Shopware\Core\Checkout\Payment\PaymentException as ShopwarePaymentException;
use Symfony\Component\HttpFoundation\Response;

class PaymentException extends ShopwarePaymentException
{
    final public const PAYMENT_VERIFICATION_PENDING = 'PAYMENT_VERIFICATION_PENDING';

    public static function paymentVerificationPending(): self
    {
        return new self(
            Response::HTTP_ACCEPTED,
            self::PAYMENT_VERIFICATION_PENDING,
            'Payment verification is pending. Verification will be retried shortly.'
        );
    }
}
