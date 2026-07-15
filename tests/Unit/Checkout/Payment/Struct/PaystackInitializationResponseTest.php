<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Struct;

use Kommandhub\PaystackSW\Checkout\Payment\Struct\PaystackInitializationResponse;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PaystackInitializationResponse::class)]
class PaystackInitializationResponseTest extends TestCase
{
    public function testStruct(): void
    {
        $authorizationUrl = 'https://checkout.paystack.com/abcdef';
        $reference = 'reference-123';
        $accessCode = 'access-code-456';

        $response = new PaystackInitializationResponse(
            $authorizationUrl,
            $reference,
            $accessCode
        );

        $this->assertEquals($authorizationUrl, $response->getAuthorizationUrl());
        $this->assertEquals($reference, $response->getReference());
        $this->assertEquals($accessCode, $response->getAccessCode());
    }

    public function testStructWithNullAccessCode(): void
    {
        $authorizationUrl = 'https://checkout.paystack.com/abcdef';
        $reference = 'reference-123';

        $response = new PaystackInitializationResponse(
            $authorizationUrl,
            $reference
        );

        $this->assertEquals($authorizationUrl, $response->getAuthorizationUrl());
        $this->assertEquals($reference, $response->getReference());
        $this->assertNull($response->getAccessCode());
    }
}
