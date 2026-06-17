<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Presentation\Listener;

use Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\EntityHandler\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundReader;
use Kommandhub\PaystackSW\Webhook\Domain\Event\RefundPendingEvent;
use Kommandhub\PaystackSW\Webhook\Domain\Event\RefundProcessedEvent;
use Kommandhub\PaystackSW\Webhook\Application\Service\RefundInitializeService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentRefundProcessor;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class WebhookEventListener
{
    public function __construct(
        private RefundInitializeService $refundInitializeService,
        private PaymentRefundProcessor $paymentRefundProcessor,
        private OrderTransactionCaptureRefundReader $orderTransactionCaptureRefundReader,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Creates the Shopware capture/refund records when Paystack
     * reports a refund has been queued.
     */
    #[AsEventListener(RefundPendingEvent::class)]
    public function onRefundPendingEvent(
        RefundPendingEvent $event
    ): void {
        $this->refundInitializeService->handle(
            $event->getData(),
            $event->getContext()
        );
    }

    /**
     * Finalizes a previously initialized refund once Paystack
     * confirms the refund has been processed.
     *
     * @throws \Throwable
     */
    #[AsEventListener(RefundProcessedEvent::class)]
    public function onRefundProcessedEvent(
        RefundProcessedEvent $event
    ): void {
        $data = $event->getData();
        $context = $event->getContext();

        $transactionReference = (string)($data['transaction_reference'] ?? '');

        if ($transactionReference === '') {
            return;
        }

        $refundId = (string)($data['id'] ?? '');

        if ($refundId === '') {
            return;
        }

        $refundReference = RefundInitializeService::buildExternalReference(
            $transactionReference,
            $refundId
        );

        $shopwareRefundId = $this->findRefundId(
            $refundReference,
            $context
        );

        /**
         * Safety net:
         * Some merchants may receive refund.processed before
         * refund.pending. Ensure the refund entity exists.
         */
        if ($shopwareRefundId === null) {
            $this->refundInitializeService->handle(
                $data,
                $context
            );

            $shopwareRefundId = $this->findRefundId(
                $refundReference,
                $context
            );
        }

        if ($shopwareRefundId === null) {
            $this->logger->warning(
                '[Paystack] Unable to locate refund entity after initialization.',
                [
                    'paystack_refund_id' => $refundReference,
                ]
            );

            return;
        }

        try {
            $this->paymentRefundProcessor->processRefund(
                $shopwareRefundId,
                $context
            );
        } catch (\Throwable $exception) {
            $this->logger->error(
                '[Paystack] Failed to process Shopware refund.',
                [
                    'refund_id' => $shopwareRefundId,
                    'paystack_refund_id' => $refundReference,
                    'exception' => $exception,
                ]
            );

            throw $exception;
        }
    }

    private function findRefundId(
        string $externalRefundId,
        Context $context
    ): ?string {
        $criteria = new Criteria();

        $criteria->addFilter(
            new EqualsFilter(
                'externalReference',
                $externalRefundId
            )
        );
        $criteria->addFields(['id']);
        $criteria->setLimit(1);

        return $this->orderTransactionCaptureRefundReader->readIdOfOne($criteria, $context);
    }
}
