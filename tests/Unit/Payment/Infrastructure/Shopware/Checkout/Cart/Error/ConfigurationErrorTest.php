<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Payment\Infrastructure\Shopware\Checkout\Cart\Error;

use Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\Checkout\Cart\Error\ConfigurationError;
use PHPUnit\Framework\TestCase;

class ConfigurationErrorTest extends TestCase
{
    public function testErrorProperties(): void
    {
        $error = new ConfigurationError();

        $this->assertSame('paystack-configuration-error', $error->getId());
        $this->assertSame('checkout.paystackConfigurationError', $error->getMessageKey());
        $this->assertTrue($error->isPersistent());
        $this->assertSame(20, $error->getLevel()); // LEVEL_ERROR = 20
        $this->assertTrue($error->blockOrder());
        $this->assertSame([], $error->getParameters());
    }
}
