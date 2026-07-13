<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Integration\Webhook\Controller;

use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Kommandhub\PaystackSW\Setting\Service\Config;
use Kommandhub\PaystackSW\Webhook\Service\WebhookEventFactory;
use Kommandhub\PaystackSW\Webhook\Service\WebhookProcessor;
use Kommandhub\PaystackSW\Webhook\Controller\WebhookController;
use Kommandhub\PaystackSW\Webhook\Service\WebhookSignatureValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[CoversClass(WebhookController::class)]
#[UsesClass(WebhookProcessor::class)]
#[UsesClass(WebhookSignatureValidator::class)]
class WebhookControllerTest extends TestCase
{
    private Config $config;
    private WebhookProcessor $webhookProcessor;
    private WebhookSignatureValidator $signatureValidator;
    private EventDispatcherInterface $eventDispatcher;
    private ConfigurableLogger $logger;
    private WebhookEventFactory $eventFactory;
    private WebhookController $controller;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->signatureValidator = new WebhookSignatureValidator($this->config);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(ConfigurableLogger::class);
        $this->eventFactory = $this->createMock(WebhookEventFactory::class);

        $this->webhookProcessor = new WebhookProcessor(
            $this->signatureValidator,
            $this->eventDispatcher,
            $this->logger,
            $this->eventFactory
        );

        $this->controller = new WebhookController(
            $this->webhookProcessor,
            $this->logger
        );
    }

    public function testWebhookRequestWithSignature(): void
    {
        $secret = 'sk_test_your_secret_key_here';
        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'id' => 3029679712,
                'domain' => 'test',
                'status' => 'success',
                'reference' => 'ref_123456',
                'amount' => 10000,
                'currency' => 'NGN',
                'customer' => [
                    'email' => 'john@example.com',
                ],
            ],
        ]);

        $signature = hash_hmac('sha512', $payload, $secret);

        $request = new Request([], [], [], [], [], [], $payload);
        $request->setMethod('POST');
        $request->headers->set('x-paystack-signature', $signature);
        $request->headers->set('Content-Type', 'application/json');

        $context = Context::createDefaultContext();

        $this->config->method('getBool')->with('enableSandbox')->willReturn(true);
        $this->config->method('getString')->with('apiSecretKeySandbox')->willReturn($secret);

        $this->eventFactory->method('create')
            ->willReturn($this->createMock(\Kommandhub\PaystackSW\Webhook\Event\ChargeSuccessEvent::class));

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch');

        $response = $this->controller->execute($request, $context);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testWebhookRequestWithInvalidSignature(): void
    {
        $request = new Request([], [], [], [], [], [], '{}');
        $request->setMethod('POST');
        $request->headers->set('x-paystack-signature', 'invalid-signature');
        $context = Context::createDefaultContext();

        $this->config->method('getBool')->willReturn(false);
        $this->config->method('getString')->willReturn('secret');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('signature validation failed'));

        $response = $this->controller->execute($request, $context);

        $this->assertEquals(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testWebhookRequestWithInvalidPayload(): void
    {
        $secret = 'secret';
        $payload = 'invalid-json';
        $signature = hash_hmac('sha512', $payload, $secret);

        $request = new Request([], [], [], [], [], [], $payload);
        $request->setMethod('POST');
        $request->headers->set('x-paystack-signature', $signature);
        $context = Context::createDefaultContext();

        $this->config->method('getBool')->willReturn(false);
        $this->config->method('getString')->willReturn($secret);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Invalid webhook payload'));

        $response = $this->controller->execute($request, $context);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testWebhookRequestWithUnexpectedError(): void
    {
        $request = $this->createMock(Request::class);
        $context = Context::createDefaultContext();

        // Use a mock for WebhookProcessor to throw a generic exception
        $webhookProcessor = $this->createMock(WebhookProcessor::class);
        $webhookProcessor->method('process')
            ->willThrowException(new \Exception('Unexpected error'));

        $controller = new WebhookController(
            $webhookProcessor,
            $this->logger
        );

        $this->logger->expects($this->once())
            ->method('error')
            ->with($this->stringContains('Webhook processing failed'));

        $response = $controller->execute($request, $context);

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }
}
