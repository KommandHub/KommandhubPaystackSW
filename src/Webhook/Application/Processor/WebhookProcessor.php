<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Application\Processor;

use Kommandhub\PaystackSW\Webhook\Application\Factory\WebhookEventFactory;
use Kommandhub\PaystackSW\Webhook\Application\Service\WebhookSignatureValidator;
use Kommandhub\PaystackSW\Core\Logging\ConfigurableLogger;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WebhookProcessor
{
    public function __construct(
        private readonly WebhookSignatureValidator $signatureValidator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ConfigurableLogger $logger,
        private readonly WebhookEventFactory $eventFactory
    ) {
    }

    public function process(Request $request, Context $context): void
    {
        $this->signatureValidator->validate($request);

        $payload = json_decode((string)$request->getContent(), true);

        if (!is_array($payload) || !isset($payload['event']) || !isset($payload['data'])) {
            throw new BadRequestHttpException('Invalid webhook payload.');
        }

        $eventName = is_scalar($payload['event']) ? (string)$payload['event'] : '';
        $data = is_array($payload['data']) ? $payload['data'] : [];

        $event = $this->eventFactory->create($eventName, $data, $context);

        if ($event === null) {
            $this->logger->info(sprintf('[Paystack] Unhandled webhook event: %s', $eventName));

            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}
