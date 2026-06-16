<?php

namespace Kommandhub\PaystackSW\Tests\Unit\Exceptions;

use Kommandhub\PaystackSW\Exceptions\PaymentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(PaymentException::class)]
class PaymentExceptionTest extends TestCase
{
    public function testPaymentVerificationPending(): void
    {
        $exception = PaymentException::paymentVerificationPending();

        $this->assertInstanceOf(PaymentException::class, $exception);
        $this->assertEquals(Response::HTTP_ACCEPTED, $exception->getStatusCode());
        $this->assertEquals(PaymentException::PAYMENT_VERIFICATION_PENDING, $exception->getErrorCode());
        $this->assertEquals('Payment verification is pending. Verification will be retried shortly.', $exception->getMessage());
    }
}
