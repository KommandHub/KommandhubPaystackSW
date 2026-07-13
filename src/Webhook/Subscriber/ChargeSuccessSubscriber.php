<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Subscriber;

use Kommandhub\PaystackSW\Checkout\Payment\Service\FinalizeProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Webhook\Event\ChargeSuccessEvent;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;

final readonly class ChargeSuccessSubscriber
{
    public function __construct(
        private OrderTransactionService $orderTransactionService,
        private FinalizeProcessor $finalizeProcessor,
        private ConfigurableLogger $logger,
    ) {
    }

    #[AsEventListener(ChargeSuccessEvent::class)]
    public function onChargeSuccessEvent(ChargeSuccessEvent $event): void
    {
        $data = $event->getData();
        $context = $event->getContext();
        $reference = (string)($data['reference'] ?? '');

        if ($reference === '') {
            $this->logger->warning('[Paystack] Charge success webhook is missing a reference.');

            return;
        }

        $transaction = $this->orderTransactionService->findOneByPaystackReference($reference, $context);

        if ($transaction === null) {
            $this->logger->warning('[Paystack] Could not find transaction for charge success webhook.', [
                'reference' => $reference,
            ]);

            return;
        }

        $this->finalizeProcessor->process(
            new Request(['reference' => $reference]),
            new PaymentTransactionStruct($transaction->getId()),
            $context,
            $transaction
        );
    }
}
