<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Util;

use Kommandhub\PaystackSW\Core\Util\PaystackConstants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaystackConstants::class)]
class PaystackConstantsTest extends TestCase
{
    public function testConstants(): void
    {
        $this->assertEquals('paystack_reference', PaystackConstants::FIELD_REFERENCE);
        $this->assertEquals('paystack_transaction_id', PaystackConstants::FIELD_TRANSACTION_ID);
        $this->assertEquals('paystack_payment_type', PaystackConstants::FIELD_PAYMENT_TYPE);
        $this->assertEquals('paystack_transaction_fee', PaystackConstants::FIELD_TRANSACTION_FEE);
        $this->assertEquals('paystack_amount', PaystackConstants::FIELD_AMOUNT);
        $this->assertEquals('paystack_currency', PaystackConstants::FIELD_CURRENCY);
        $this->assertEquals('paystack_verified_at', PaystackConstants::FIELD_VERIFIED_AT);
    }
}
