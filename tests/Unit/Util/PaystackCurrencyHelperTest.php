<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Util;

use Kommandhub\PaystackSW\Core\Util\PaystackCurrencyHelper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaystackCurrencyHelper::class)]
class PaystackCurrencyHelperTest extends TestCase
{
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
