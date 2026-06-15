<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Service\Webhook;

use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class WebhookProcessor
{
    public function __construct(
        private readonly WebhookSignatureValidator $signatureValidator,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly WebhookEventFactory $eventFactory
    ) {
    }

    public function process(Request $request, Context $context): void
    {
        $this->signatureValidator->validate($request);

        $payload = json_decode((string) $request->getContent(), true);
        if (!$payload || !isset($payload['event'], $payload['data'])) {
            throw new BadRequestHttpException('Invalid webhook payload.');
        }

        $eventName = (string) $payload['event'];
        $data = (array) $payload['data'];

        $event = $this->eventFactory->create($eventName, $data, $context);

        if ($event === null) {
            $this->logger->info(sprintf('[Paystack] Unhandled webhook event: %s', $eventName));
            return;
        }

        $this->eventDispatcher->dispatch($event);
    }
}
