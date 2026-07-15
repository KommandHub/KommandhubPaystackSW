<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Enum;

use Kommandhub\PaystackSW\Checkout\Payment\Enum\PaystackTransactionStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaystackTransactionStatus::class)]
class PaystackTransactionStatusTest extends TestCase
{
    public function testIsFinal(): void
    {
        $this->assertTrue(PaystackTransactionStatus::SUCCESS->isFinal());
        $this->assertTrue(PaystackTransactionStatus::ABANDONED->isFinal());
        $this->assertTrue(PaystackTransactionStatus::FAILED->isFinal());
        $this->assertTrue(PaystackTransactionStatus::REVERSED->isFinal());

        $this->assertFalse(PaystackTransactionStatus::ONGOING->isFinal());
        $this->assertFalse(PaystackTransactionStatus::PENDING->isFinal());
        $this->assertFalse(PaystackTransactionStatus::PROCESSING->isFinal());
        $this->assertFalse(PaystackTransactionStatus::QUEUED->isFinal());
    }

    public function testValues(): void
    {
        $this->assertEquals('success', PaystackTransactionStatus::SUCCESS->value);
        $this->assertEquals('abandoned', PaystackTransactionStatus::ABANDONED->value);
        $this->assertEquals('failed', PaystackTransactionStatus::FAILED->value);
        $this->assertEquals('reversed', PaystackTransactionStatus::REVERSED->value);
        $this->assertEquals('ongoing', PaystackTransactionStatus::ONGOING->value);
        $this->assertEquals('pending', PaystackTransactionStatus::PENDING->value);
        $this->assertEquals('processing', PaystackTransactionStatus::PROCESSING->value);
        $this->assertEquals('queued', PaystackTransactionStatus::QUEUED->value);
    }
}
