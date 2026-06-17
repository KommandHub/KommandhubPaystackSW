<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Webhook\Application\Service;

use Kommandhub\PaystackSW\Core\Config\Config;
use Kommandhub\PaystackSW\Webhook\Application\Service\WebhookSignatureValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[CoversClass(WebhookSignatureValidator::class)]
class WebhookSignatureValidatorTest extends TestCase
{
    private Config&MockObject $config;
    private WebhookSignatureValidator $validator;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->validator = new WebhookSignatureValidator($this->config);
    }

    public function testValidateSuccess(): void
    {
        $payload = '{"test":"data"}';
        $secret = 'test-secret';
        $signature = hash_hmac('sha512', $payload, $secret);

        $request = new Request([], [], [], [], [], [], $payload);
        $request->headers->set('x-paystack-signature', $signature);

        $this->config->method('getBool')->with('enableSandbox')->willReturn(false);
        $this->config->method('getString')->with('apiSecretKey')->willReturn($secret);

        $this->validator->validate($request);
        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateSuccessSandbox(): void
    {
        $payload = '{"test":"data"}';
        $secret = 'test-secret-sandbox';
        $signature = hash_hmac('sha512', $payload, $secret);

        $request = new Request([], [], [], [], [], [], $payload);
        $request->headers->set('x-paystack-signature', $signature);

        $this->config->method('getBool')->with('enableSandbox')->willReturn(true);
        $this->config->method('getString')->with('apiSecretKeySandbox')->willReturn($secret);

        $this->validator->validate($request);
        $this->assertTrue(true); // No exception thrown
    }

    public function testValidateThrowsOnMissingHeader(): void
    {
        $request = new Request();

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Missing Paystack signature header.');

        $this->validator->validate($request);
    }

    public function testValidateThrowsOnMissingSecret(): void
    {
        $request = new Request();
        $request->headers->set('x-paystack-signature', 'some-sig');

        $this->config->method('getBool')->willReturn(false);
        $this->config->method('getString')->willReturn('');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Paystack API secret key not configured.');

        $this->validator->validate($request);
    }

    public function testValidateThrowsOnInvalidSignature(): void
    {
        $payload = '{"test":"data"}';
        $request = new Request([], [], [], [], [], [], $payload);
        $request->headers->set('x-paystack-signature', 'invalid-sig');

        $this->config->method('getBool')->willReturn(false);
        $this->config->method('getString')->willReturn('secret');

        $this->expectException(AccessDeniedHttpException::class);
        $this->expectExceptionMessage('Invalid Paystack signature.');

        $this->validator->validate($request);
    }
}
