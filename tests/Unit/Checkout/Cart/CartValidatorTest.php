<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Cart;

use Kommandhub\PaystackSW\Setting\Service\Config;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Kommandhub\PaystackSW\Checkout\Cart\CartValidator;
use Kommandhub\PaystackSW\Checkout\Cart\Error\ConfigurationError;
use Kommandhub\PaystackSW\Checkout\Payment\Handler\PaystackPaymentHandler;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class CartValidatorTest extends TestCase
{
    private Config $config;
    private ConfigurableLogger $logger;
    private CartValidator $validator;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->logger = $this->createMock(ConfigurableLogger::class);
        $this->validator = new CartValidator($this->config, $this->logger);
    }

    public function testValidateWithDifferentPaymentMethod(): void
    {
        $cart = $this->createMock(Cart::class);
        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setHandlerIdentifier('other_handler');

        $context->method('getPaymentMethod')->willReturn($paymentMethod);

        $this->validator->validate($cart, $errors, $context);

        $this->assertCount(0, $errors);
    }

    public function testValidateWithMissingLiveKey(): void
    {
        $cart = $this->createMock(Cart::class);
        $cart->method('getToken')->willReturn('cart-token');
        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setHandlerIdentifier(PaystackPaymentHandler::class);

        $context->method('getPaymentMethod')->willReturn($paymentMethod);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $this->config->method('getBool')->with('enableSandbox', 'channel-id')->willReturn(false);
        $this->config->method('getString')->with('apiSecretKey', 'channel-id')->willReturn('');

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Paystack API secret key is not configured.', [
                'salesChannelId' => 'channel-id',
                'isSandbox' => false,
                'cartToken' => 'cart-token',
            ]);

        $this->validator->validate($cart, $errors, $context);

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(ConfigurationError::class, $errors->first());
    }

    public function testValidateWithMissingSandboxKey(): void
    {
        $cart = $this->createMock(Cart::class);
        $cart->method('getToken')->willReturn('cart-token');
        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setHandlerIdentifier(PaystackPaymentHandler::class);

        $context->method('getPaymentMethod')->willReturn($paymentMethod);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $this->config->method('getBool')->with('enableSandbox', 'channel-id')->willReturn(true);
        $this->config->method('getString')->with('apiSecretKeySandbox', 'channel-id')->willReturn('');

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Paystack API secret key is not configured.', [
                'salesChannelId' => 'channel-id',
                'isSandbox' => true,
                'cartToken' => 'cart-token',
            ]);

        $this->validator->validate($cart, $errors, $context);

        $this->assertCount(1, $errors);
        $this->assertInstanceOf(ConfigurationError::class, $errors->first());
    }

    public function testValidateWithValidLiveKey(): void
    {
        $cart = $this->createMock(Cart::class);
        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setHandlerIdentifier(PaystackPaymentHandler::class);

        $context->method('getPaymentMethod')->willReturn($paymentMethod);
        $context->method('getSalesChannelId')->willReturn('channel-id');

        $this->config->method('getBool')->with('enableSandbox', 'channel-id')->willReturn(false);
        $this->config->method('getString')->with('apiSecretKey', 'channel-id')->willReturn('sk_live_123');

        $this->validator->validate($cart, $errors, $context);

        $this->assertCount(0, $errors);
    }

    public function testValidateWithExistingConfigurationError(): void
    {
        $cart = $this->createMock(Cart::class);
        $errors = new ErrorCollection();
        $context = $this->createMock(SalesChannelContext::class);
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setHandlerIdentifier(PaystackPaymentHandler::class);

        $context->method('getPaymentMethod')->willReturn($paymentMethod);

        $cart->method('getErrors')->willReturn(new ErrorCollection([new ConfigurationError()]));

        $this->validator->validate($cart, $errors, $context);

        $this->assertCount(0, $errors);
    }
}
