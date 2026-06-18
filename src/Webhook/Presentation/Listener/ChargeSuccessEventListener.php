<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Presentation\Listener;

use Kommandhub\PaystackSW\Payment\Application\Processor\FinalizeProcessor;
use Kommandhub\PaystackSW\Payment\Application\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Webhook\Domain\Event\ChargeSuccessEvent;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;

final readonly class ChargeSuccessEventListener
{
    public function __construct(
        private OrderTransactionService $orderTransactionService,
        private FinalizeProcessor $finalizeProcessor,
        private LoggerInterface $logger,
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
            $context
        );
    }
}
