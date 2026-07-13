<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Handler;

use Kommandhub\PaystackSW\Checkout\Payment\Handler\AbstractPaystackPaymentHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Framework\Context;

#[CoversClass(AbstractPaystackPaymentHandler::class)]
class AbstractPaystackPaymentHandlerTest extends TestCase
{
    public function testSupportsReturnsFalse(): void
    {
        $handler = new class() extends AbstractPaystackPaymentHandler {
            public function pay(\Symfony\Component\HttpFoundation\Request $request, \Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct $transaction, \Shopware\Core\Framework\Context $context, ?\Shopware\Core\Framework\Struct\Struct $validateStruct): ?\Symfony\Component\HttpFoundation\RedirectResponse
            {
                return null;
            }
        };

        $this->assertFalse(
            $handler->supports(
                PaymentHandlerType::RECURRING,
                'payment-method-id',
                Context::createDefaultContext()
            )
        );
    }
}
