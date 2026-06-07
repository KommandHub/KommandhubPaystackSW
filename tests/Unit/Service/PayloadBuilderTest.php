<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Service;

use Kommandhub\PaystackSW\Service\Config;
use Kommandhub\PaystackSW\Service\PayloadBuilder;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

class PayloadBuilderTest extends TestCase
{
    private Config $config;
    private PayloadBuilder $payloadBuilder;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->payloadBuilder = new PayloadBuilder($this->config);
    }

    public function testBuildSuccessful(): void
    {
        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $transaction = $this->createMock(PaymentTransactionStruct::class);
        $order = $this->createMock(OrderEntity::class);
        $customer = $this->createMock(OrderCustomerEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $amount = $this->createMock(CalculatedPrice::class);

        $orderTransaction->method('getOrder')->willReturn($order);
        $orderTransaction->method('getAmount')->willReturn($amount);
        $amount->method('getTotalPrice')->willReturn(100.50);

        $order->method('getOrderCustomer')->willReturn($customer);
        $order->method('getCurrency')->willReturn($currency);
        $order->method('getSalesChannelId')->willReturn('sales-channel-id');

        $customer->method('getEmail')->willReturn('test@example.com');
        $currency->method('getIsoCode')->willReturn('NGN');

        $transaction->method('getReturnUrl')->willReturn('https://return.url');

        $this->config->method('get')->willReturnMap([
            ['paymentOptions', null, 'sales-channel-id', ['card', 'bank']],
            ['metaData', [], 'sales-channel-id', []],
        ]);

        $payload = $this->payloadBuilder->build($orderTransaction, $transaction);

        $expectedPayload = [
            'amount' => 10050,
            'currency' => 'NGN',
            'email' => 'test@example.com',
            'callback_url' => 'https://return.url',
            'channels' => ['card', 'bank'],
            'metadata' => [
                'cancel_action' => 'https://return.url',
            ],
        ];

        $this->assertEquals($expectedPayload, $payload);
    }

    public function testBuildThrowsExceptionWhenOrderIsMissing(): void
    {
        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $transaction = $this->createMock(PaymentTransactionStruct::class);

        $orderTransaction->method('getOrder')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Order information is missing for the payment transaction.');

        $this->payloadBuilder->build($orderTransaction, $transaction);
    }

    public function testBuildThrowsExceptionWhenCustomerIsMissing(): void
    {
        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $transaction = $this->createMock(PaymentTransactionStruct::class);
        $order = $this->createMock(OrderEntity::class);

        $orderTransaction->method('getOrder')->willReturn($order);
        $order->method('getOrderCustomer')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Customer information is missing for the order.');

        $this->payloadBuilder->build($orderTransaction, $transaction);
    }

    public function testBuildThrowsExceptionWhenCurrencyIsMissing(): void
    {
        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $transaction = $this->createMock(PaymentTransactionStruct::class);
        $order = $this->createMock(OrderEntity::class);
        $customer = $this->createMock(OrderCustomerEntity::class);

        $orderTransaction->method('getOrder')->willReturn($order);
        $order->method('getOrderCustomer')->willReturn($customer);
        $order->method('getCurrency')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Currency information is missing for the order.');

        $this->payloadBuilder->build($orderTransaction, $transaction);
    }

    public function testBuildThrowsExceptionWhenReturnUrlIsMissing(): void
    {
        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $transaction = $this->createMock(PaymentTransactionStruct::class);
        $order = $this->createMock(OrderEntity::class);
        $customer = $this->createMock(OrderCustomerEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);

        $orderTransaction->method('getOrder')->willReturn($order);
        $order->method('getOrderCustomer')->willReturn($customer);
        $order->method('getCurrency')->willReturn($currency);

        $transaction->method('getReturnUrl')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Return URL is missing in the payment transaction struct.');

        $this->payloadBuilder->build($orderTransaction, $transaction);
    }

    public function testBuildWithMetadata(): void
    {
        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $transaction = $this->createMock(PaymentTransactionStruct::class);
        $order = $this->createMock(OrderEntity::class);
        $customer = $this->createMock(OrderCustomerEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $amount = $this->createMock(CalculatedPrice::class);
        $billingAddress = $this->createMock(OrderAddressEntity::class);
        $country = $this->createMock(CountryEntity::class);
        $lineItem = $this->createMock(OrderLineItemEntity::class);
        $lineItems = new OrderLineItemCollection([$lineItem]);

        $orderTransaction->method('getOrder')->willReturn($order);
        $orderTransaction->method('getAmount')->willReturn($amount);
        $amount->method('getTotalPrice')->willReturn(100.00);

        $order->method('getOrderCustomer')->willReturn($customer);
        $order->method('getCurrency')->willReturn($currency);
        $order->method('getSalesChannelId')->willReturn('sales-channel-id');
        $order->method('getOrderNumber')->willReturn('ORDER-123');
        $order->method('getBillingAddress')->willReturn($billingAddress);
        $order->method('getLineItems')->willReturn($lineItems);

        $customer->method('getEmail')->willReturn('test@example.com');
        $customer->method('getFirstName')->willReturn('John');
        $customer->method('getLastName')->willReturn('Doe');

        $currency->method('getIsoCode')->willReturn('NGN');

        $billingAddress->method('getFirstName')->willReturn('John');
        $billingAddress->method('getLastName')->willReturn('Doe');
        $billingAddress->method('getStreet')->willReturn('Main St 123');
        $billingAddress->method('getZipcode')->willReturn('12345');
        $billingAddress->method('getCity')->willReturn('Lagos');
        $billingAddress->method('getCountry')->willReturn($country);
        $billingAddress->method('getPhoneNumber')->willReturn('08012345678');
        $country->method('getName')->willReturn('Nigeria');

        $lineItem->method('getQuantity')->willReturn(2);
        $lineItem->method('getLabel')->willReturn('Test Product');

        $transaction->method('getReturnUrl')->willReturn('https://return.url');

        $this->config->method('get')->willReturnMap([
            ['paymentOptions', null, 'sales-channel-id', ['card']],
            ['metaData', [], 'sales-channel-id', ['orderId', 'customerName', 'customerPhone', 'products']],
        ]);

        $payload = $this->payloadBuilder->build($orderTransaction, $transaction);

        $expectedMetadata = [
            'cancel_action' => 'https://return.url',
            'custom_fields' => [
                [
                    'display_name' => 'Order ID',
                    'variable_name' => 'order_id',
                    'value' => 'ORDER-123',
                ],
                [
                    'display_name' => 'Customer Name',
                    'variable_name' => 'customer_name',
                    'value' => 'John Doe',
                ],
                [
                    'display_name' => 'Customer Phone',
                    'variable_name' => 'customer_phone',
                    'value' => '08012345678',
                ],
                [
                    'display_name' => 'Product(s) Purchased',
                    'variable_name' => 'products_purchased',
                    'value' => '2x Test Product',
                ],
            ],
        ];

        $this->assertEquals($expectedMetadata, $payload['metadata']);
    }

    public function testBuildWithSplitPayment(): void
    {
        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $transaction = $this->createMock(PaymentTransactionStruct::class);
        $order = $this->createMock(OrderEntity::class);
        $customer = $this->createMock(OrderCustomerEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $amount = $this->createMock(CalculatedPrice::class);

        $orderTransaction->method('getOrder')->willReturn($order);
        $orderTransaction->method('getAmount')->willReturn($amount);
        $amount->method('getTotalPrice')->willReturn(100.00);

        $order->method('getOrderCustomer')->willReturn($customer);
        $order->method('getCurrency')->willReturn($currency);
        $order->method('getSalesChannelId')->willReturn('sales-channel-id');

        $customer->method('getEmail')->willReturn('test@example.com');
        $currency->method('getIsoCode')->willReturn('NGN');

        $transaction->method('getReturnUrl')->willReturn('https://return.url');

        $this->config->method('get')->willReturnMap([
            ['paymentOptions', null, 'sales-channel-id', ['card']],
            ['metaData', [], 'sales-channel-id', []],
            ['splitPaymentTransactionCharge', null, 'sales-channel-id', 50],
        ]);

        $this->config->method('getBool')->willReturnMap([
            ['enableSplitPayment', 'sales-channel-id', true],
        ]);

        $this->config->method('getString')->willReturnMap([
            ['subaccountCode', 'sales-channel-id', 'ACCT_12345'],
            ['splitCode', 'sales-channel-id', ''],
            ['paystackChargesBearer', 'sales-channel-id', 'subaccount'],
        ]);

        $payload = $this->payloadBuilder->build($orderTransaction, $transaction);

        $this->assertEquals('ACCT_12345', $payload['subaccount']);
        $this->assertEquals(5000, $payload['transaction_charge']);
        $this->assertEquals('subaccount', $payload['bearer']);
        $this->assertArrayNotHasKey('split_code', $payload);
    }

    public function testBuildWithSplitGroup(): void
    {
        $orderTransaction = $this->createMock(OrderTransactionEntity::class);
        $transaction = $this->createMock(PaymentTransactionStruct::class);
        $order = $this->createMock(OrderEntity::class);
        $customer = $this->createMock(OrderCustomerEntity::class);
        $currency = $this->createMock(CurrencyEntity::class);
        $amount = $this->createMock(CalculatedPrice::class);

        $orderTransaction->method('getOrder')->willReturn($order);
        $orderTransaction->method('getAmount')->willReturn($amount);
        $amount->method('getTotalPrice')->willReturn(100.00);

        $order->method('getOrderCustomer')->willReturn($customer);
        $order->method('getCurrency')->willReturn($currency);
        $order->method('getSalesChannelId')->willReturn('sales-channel-id');

        $customer->method('getEmail')->willReturn('test@example.com');
        $currency->method('getIsoCode')->willReturn('NGN');

        $transaction->method('getReturnUrl')->willReturn('https://return.url');

        $this->config->method('get')->willReturnMap([
            ['paymentOptions', null, 'sales-channel-id', ['card']],
            ['metaData', [], 'sales-channel-id', []],
            ['splitPaymentTransactionCharge', null, 'sales-channel-id', 100],
        ]);

        $this->config->method('getBool')->willReturnMap([
            ['enableSplitPayment', 'sales-channel-id', true],
        ]);

        $this->config->method('getString')->willReturnMap([
            ['subaccountCode', 'sales-channel-id', ''],
            ['splitCode', 'sales-channel-id', 'SPL_67890'],
            ['paystackChargesBearer', 'sales-channel-id', 'account'],
        ]);

        $payload = $this->payloadBuilder->build($orderTransaction, $transaction);

        $this->assertEquals('SPL_67890', $payload['split_code']);
        $this->assertEquals(10000, $payload['transaction_charge']);
        $this->assertEquals('account', $payload['bearer']);
        $this->assertArrayNotHasKey('subaccount', $payload);
    }
}
