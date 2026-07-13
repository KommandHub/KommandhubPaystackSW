<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Webhook\Service;

use Kommandhub\PaystackSW\Webhook\Event\ChargeSuccessEvent;
use Kommandhub\PaystackSW\Webhook\Event\WebhookEvent;
use Kommandhub\PaystackSW\Webhook\Service\WebhookEventFactory;
use Kommandhub\PaystackSW\Webhook\Service\WebhookProcessor;
use Kommandhub\PaystackSW\Webhook\Service\WebhookSignatureValidator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[CoversClass(WebhookProcessor::class)]
#[UsesClass(WebhookEvent::class)]
#[UsesClass(ChargeSuccessEvent::class)]
class WebhookProcessorTest extends TestCase
{
    private WebhookSignatureValidator&MockObject $signatureValidator;
    private EventDispatcherInterface&MockObject $eventDispatcher;
    private ConfigurableLogger&MockObject $logger;
    private WebhookEventFactory&MockObject $eventFactory;
    private WebhookProcessor $processor;

    protected function setUp(): void
    {
        $this->signatureValidator = $this->createMock(WebhookSignatureValidator::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(ConfigurableLogger::class);
        $this->eventFactory = $this->createMock(WebhookEventFactory::class);

        $this->processor = new WebhookProcessor(
            $this->signatureValidator,
            $this->eventDispatcher,
            $this->logger,
            $this->eventFactory
        );
    }

    public function testProcessSuccess(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'event' => 'charge.success',
            'data' => ['id' => '123'],
        ]));
        $context = Context::createDefaultContext();

        $this->signatureValidator->expects($this->once())
            ->method('validate')
            ->with($request);

        $event = new ChargeSuccessEvent(['id' => '123'], $context);

        $this->eventFactory->expects($this->once())
            ->method('create')
            ->with('charge.success', ['id' => '123'], $context)
            ->willReturn($event);

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($event);

        $this->processor->process($request, $context);
    }

    public function testProcessThrowsOnInvalidPayload(): void
    {
        $request = new Request([], [], [], [], [], [], 'invalid-json');
        $context = Context::createDefaultContext();

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid webhook payload.');

        $this->processor->process($request, $context);
    }

    public function testProcessLogsUnhandledEvent(): void
    {
        $request = new Request([], [], [], [], [], [], json_encode([
            'event' => 'unknown.event',
            'data' => [],
        ]));
        $context = Context::createDefaultContext();

        $this->eventFactory->method('create')->willReturn(null);

        $this->logger->expects($this->once())
            ->method('info')
            ->with($this->stringContains('Unhandled webhook event: unknown.event'));

        $this->processor->process($request, $context);
    }
}
