<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Util;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;

/**
 * Resolves the ISO currency code of the order behind an order transaction.
 *
 * Money is converted to/from Paystack's minor units using per-currency decimals,
 * so converting with the wrong currency silently changes the amount by a factor
 * of 10^n. Guessing a default here has already caused a production defect (the
 * webhook refund path resolved every amount as NGN because the `order`
 * association was not loaded), so this resolver deliberately **fails closed**:
 * if the currency cannot be resolved, callers must abort rather than assume one.
 *
 * Callers are responsible for loading the `order.currency` association.
 */
final class OrderCurrencyResolver
{
    /**
     * @return string|null The ISO code, or null when it cannot be resolved
     *                     (order/currency association missing or not loaded).
     */
    public static function resolve(OrderTransactionEntity $transaction): ?string
    {
        return $transaction->getOrder()?->getCurrency()?->getIsoCode();
    }

    /**
     * @throws \RuntimeException When the currency cannot be resolved.
     */
    public static function resolveOrFail(OrderTransactionEntity $transaction): string
    {
        $isoCode = self::resolve($transaction);

        if ($isoCode === null) {
            throw new \RuntimeException(sprintf(
                'Unable to resolve the order currency for transaction "%s". '
                . 'Refusing to convert amounts with an assumed currency; '
                . 'ensure the "order.currency" association is loaded.',
                $transaction->getId()
            ));
        }

        return $isoCode;
    }
}
