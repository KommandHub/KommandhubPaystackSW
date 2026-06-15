<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Integration\Storefront\Controller;

use Kommandhub\PaystackSW\Service\Config;
use Kommandhub\PaystackSW\Service\Webhook\WebhookProcessor;
use Kommandhub\PaystackSW\Storefront\Controller\WebhookController;
use Kommandhub\PaystackSW\Service\Webhook\WebhookSignatureValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WebhookControllerTest extends TestCase
{
    private Config $config;
    private WebhookProcessor $webhookProcessor;
    private WebhookSignatureValidator $signatureValidator;
    private EventDispatcherInterface $eventDispatcher;
    private LoggerInterface $logger;
    private WebhookController $controller;

    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);
        $this->signatureValidator = new WebhookSignatureValidator($this->config);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->webhookProcessor = new WebhookProcessor(
            $this->signatureValidator,
            $this->eventDispatcher,
            $this->logger
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

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch');

        $response = $this->controller->execute($request, $context);

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
    }
}
