<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Core\Util;

final class PaystackCurrencyHelper
{
    /**
     * Currency => number of decimal places.
     *
     * @see https://en.wikipedia.org/wiki/ISO_4217
     */
    private const DECIMALS = [
        // 0 decimal currencies
        'JPY' => 0,
        'XOF' => 0,
        'XAF' => 0,
        'KMF' => 0,
        'GNF' => 0,
        'CLP' => 0,
        'RWF' => 0,
        'UGX' => 0,
        'GHS' => 2,
        'ZAR' => 2,
        'KES' => 2,
        'NGN' => 2,

        // 3 decimal currencies
        'BHD' => 3,
        'IQD' => 3,
        'JOD' => 3,
        'KWD' => 3,
        'LYD' => 3,
        'OMR' => 3,
        'TND' => 3,
    ];

    /**
     * Converts a major currency amount into the smallest currency unit
     * expected by Paystack.
     *
     * Examples:
     *  NGN 20.00 => 2000
     *  USD 20.00 => 2000
     *  JPY 20    => 20
     *  KWD 20.00 => 20000
     */
    public static function toMinorUnit(
        float $amount,
        string $currencyCode = 'NGN'
    ): int {
        $currencyCode = strtoupper($currencyCode);

        $decimals = self::DECIMALS[$currencyCode] ?? 2;

        return (int)round(
            $amount * (10 ** $decimals),
            0,
            PHP_ROUND_HALF_UP
        );
    }

    /**
     * Converts Paystack amount back to a major currency amount.
     */
    public static function fromMinorUnit(
        int $amount,
        string $currencyCode = 'NGN'
    ): float {
        $currencyCode = strtoupper($currencyCode);

        $decimals = self::DECIMALS[$currencyCode] ?? 2;

        return $amount / (10 ** $decimals);
    }
}
