<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Payment\Application\Processor;

use Kommandhub\PaystackSW\Payment\Application\Processor\RefundAggregationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RefundAggregationResult::class)]
class RefundAggregationResultTest extends TestCase
{
    public function testStruct(): void
    {
        $captures = ['capture-1' => new \stdClass()];
        $isFullyRefunded = true;

        $result = new RefundAggregationResult(
            $captures,
            $isFullyRefunded
        );

        $this->assertEquals($captures, $result->captures);
        $this->assertTrue($result->isFullyRefunded);
    }

    public function testPartialRefund(): void
    {
        $captures = [];
        $isFullyRefunded = false;

        $result = new RefundAggregationResult(
            $captures,
            $isFullyRefunded
        );

        $this->assertEquals($captures, $result->captures);
        $this->assertFalse($result->isFullyRefunded);
    }
}
