<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Util;

use Kommandhub\PaystackSW\Util\PaystackCurrencyHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaystackCurrencyHelper::class)]
class PaystackCurrencyHelperTest extends TestCase
{
    /**
     * Every currency the helper knows about, one per decimal class, plus an
     * unknown code that must fall back to 2 decimals.
     *
     * @return array<string, array{0: string}>
     */
    public static function everyCurrencyProvider(): array
    {
        $currencies = [
            // 0-decimal
            'JPY', 'XOF', 'XAF', 'KMF', 'GNF', 'CLP', 'RWF', 'UGX',
            // 2-decimal
            'NGN', 'GHS', 'ZAR', 'KES',
            // 3-decimal
            'BHD', 'IQD', 'JOD', 'KWD', 'LYD', 'OMR', 'TND',
            // unknown -> defaults to 2
            'ZZZ',
        ];

        return array_combine(
            $currencies,
            array_map(static fn (string $c): array => [$c], $currencies)
        );
    }

    /**
     * Invariant: a round-trip must be *stable* — once an amount has been
     * normalised to a currency's precision, converting it again must be a no-op.
     * An unstable conversion means an amount drifts every time it crosses the
     * Paystack boundary.
     *
     * @dataProvider everyCurrencyProvider
     */
    public function testMinorUnitRoundTripIsStable(string $currency): void
    {
        foreach ([0.0, 1.0, 60.0, 99.99, 1234.5, 0.001] as $amount) {
            $once = PaystackCurrencyHelper::fromMinorUnit(
                PaystackCurrencyHelper::toMinorUnit($amount, $currency),
                $currency
            );
            $twice = PaystackCurrencyHelper::fromMinorUnit(
                PaystackCurrencyHelper::toMinorUnit($once, $currency),
                $currency
            );

            static::assertSame(
                $once,
                $twice,
                sprintf('Round-trip is not stable for %s at amount %s', $currency, (string)$amount)
            );
            static::assertIsInt(
                PaystackCurrencyHelper::toMinorUnit($amount, $currency),
                sprintf('%s minor unit must be an integer', $currency)
            );
        }
    }

    /**
     * Invariant: an amount already expressed at the currency's precision must
     * survive a round-trip exactly. Uses one representative amount per decimal
     * class so the expectation is independent of the helper's own table.
     */
    public function testExactRoundTripForAmountsAtCurrencyPrecision(): void
    {
        $cases = [
            ['JPY', 700.0],    // 0-decimal
            ['XOF', 60.0],     // 0-decimal
            ['NGN', 99.99],    // 2-decimal
            ['ZAR', 1234.50],  // 2-decimal
            ['KWD', 10.125],   // 3-decimal
            ['ZZZ', 5.25],     // unknown -> 2-decimal default
        ];

        foreach ($cases as [$currency, $amount]) {
            static::assertSame(
                $amount,
                PaystackCurrencyHelper::fromMinorUnit(
                    PaystackCurrencyHelper::toMinorUnit($amount, $currency),
                    $currency
                ),
                sprintf('%s lost value round-tripping %s', $currency, (string)$amount)
            );
        }
    }

    /**
     * Invariant: minor units must never be negative for a positive amount, and
     * the conversion must be monotonic — a larger amount is never fewer minor
     * units. Guards against a decimals table regression.
     *
     * @dataProvider everyCurrencyProvider
     */
    public function testConversionIsMonotonicAndNonNegative(string $currency): void
    {
        $previous = -1;

        foreach ([0.0, 0.5, 1.0, 10.0, 60.0, 700.0] as $amount) {
            $minor = PaystackCurrencyHelper::toMinorUnit($amount, $currency);

            static::assertGreaterThanOrEqual(0, $minor, sprintf('%s produced negative minor units', $currency));
            static::assertGreaterThanOrEqual(
                $previous,
                $minor,
                sprintf('%s conversion is not monotonic at %s', $currency, (string)$amount)
            );

            $previous = $minor;
        }
    }

    /**
     * @dataProvider toMinorUnitProvider
     */
    public function testToMinorUnit(float $amount, string $currency, int $expected): void
    {
        $this->assertEquals($expected, PaystackCurrencyHelper::toMinorUnit($amount, $currency));
    }

    public static function toMinorUnitProvider(): array
    {
        return [
            'NGN with 2 decimals' => [20.00, 'NGN', 2000],
            'USD with 2 decimals' => [20.00, 'USD', 2000],
            'JPY with 0 decimals' => [20.00, 'JPY', 20],
            'KWD with 3 decimals' => [20.00, 'KWD', 20000],
            'Lowercase currency' => [15.50, 'ngn', 1550],
            'Rounding up' => [20.006, 'NGN', 2001],
            'Rounding down' => [20.004, 'NGN', 2000],
            'XOF with 0 decimals' => [100.00, 'XOF', 100],
            'BHD with 3 decimals' => [1.234, 'BHD', 1234],
            'GHS with 2 decimals' => [50.50, 'GHS', 5050],
            'ZAR with 2 decimals' => [100.99, 'ZAR', 10099],
            'KES with 2 decimals' => [1000.00, 'KES', 100000],
            'Unknown currency defaults to 2' => [10.00, 'ABC', 1000],
            'Default currency NGN' => [25.00, 'NGN', 2500],
        ];
    }

    public function testToMinorUnitDefault(): void
    {
        $this->assertEquals(2500, PaystackCurrencyHelper::toMinorUnit(25.00));
    }

    /**
     * @dataProvider fromMinorUnitProvider
     */
    public function testFromMinorUnit(int $amount, string $currency, float $expected): void
    {
        $this->assertEquals($expected, PaystackCurrencyHelper::fromMinorUnit($amount, $currency));
    }

    public function testFromMinorUnitDefault(): void
    {
        $this->assertEquals(25.00, PaystackCurrencyHelper::fromMinorUnit(2500));
    }

    public static function fromMinorUnitProvider(): array
    {
        return [
            'NGN from 2000 kobo' => [2000, 'NGN', 20.00],
            'JPY from 20 yen' => [20, 'JPY', 20.00],
            'KWD from 20000 fils' => [20000, 'KWD', 20.000],
            'Lowercase currency' => [1550, 'ngn', 15.50],
            'XOF from 100 CFA' => [100, 'XOF', 100.00],
            'BHD from 1234 fils' => [1234, 'BHD', 1.234],
            'GHS from 5050 pesewas' => [5050, 'GHS', 50.50],
            'ZAR from 10099 cents' => [10099, 'ZAR', 100.99],
            'KES from 100000 cents' => [100000, 'KES', 1000.00],
        ];
    }
}
