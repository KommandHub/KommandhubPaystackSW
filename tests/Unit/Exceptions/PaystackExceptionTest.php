<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Exceptions;

use Kommandhub\PaystackSW\Exceptions\PaystackException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaystackException::class)]
class PaystackExceptionTest extends TestCase
{
    public function testException(): void
    {
        $message = 'Test error message';
        $code = 400;
        $exception = new PaystackException($message, $code);

        $this->assertEquals($message, $exception->getMessage());
        $this->assertEquals($code, $exception->getCode());
    }
}
