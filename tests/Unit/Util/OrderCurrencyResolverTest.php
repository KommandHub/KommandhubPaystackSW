<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Util;

use Kommandhub\PaystackSW\Util\OrderCurrencyResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

#[CoversClass(OrderCurrencyResolver::class)]
class OrderCurrencyResolverTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function currencyProvider(): array
    {
        return [
            '2-decimal (NGN)' => ['NGN'],
            '0-decimal (XOF)' => ['XOF'],
            '3-decimal (KWD)' => ['KWD'],
            'non-African (EUR)' => ['EUR'],
        ];
    }

    #[DataProvider('currencyProvider')]
    public function testResolveReturnsTheOrderCurrency(string $isoCode): void
    {
        $transaction = $this->transactionWithCurrency($isoCode);

        static::assertSame($isoCode, OrderCurrencyResolver::resolve($transaction));
        static::assertSame($isoCode, OrderCurrencyResolver::resolveOrFail($transaction));
    }

    public function testResolveReturnsNullWhenOrderIsNotLoaded(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-id');

        static::assertNull(OrderCurrencyResolver::resolve($transaction));
    }

    public function testResolveReturnsNullWhenCurrencyIsNotLoaded(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-id');
        $transaction->setOrder(new OrderEntity());

        static::assertNull(OrderCurrencyResolver::resolve($transaction));
    }

    public function testResolveOrFailThrowsWhenCurrencyCannotBeResolved(): void
    {
        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-id');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to resolve the order currency for transaction "transaction-id"');

        OrderCurrencyResolver::resolveOrFail($transaction);
    }

    private function transactionWithCurrency(string $isoCode): OrderTransactionEntity
    {
        $currency = new CurrencyEntity();
        $currency->setIsoCode($isoCode);

        $order = new OrderEntity();
        $order->setCurrency($currency);

        $transaction = new OrderTransactionEntity();
        $transaction->setId('transaction-id');
        $transaction->setOrder($order);

        return $transaction;
    }
}
