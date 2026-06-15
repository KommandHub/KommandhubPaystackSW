<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Service\Webhook;

use Kommandhub\PaystackSW\Util\PaystackConstants;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureStates;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;

/**
 * @final
 */
final readonly class RefundInitializeService
{
    public function __construct(
        private EntityRepository $orderTransactionRepository,
        private EntityRepository $orderTransactionCaptureRepository,
        private EntityRepository $orderTransactionCaptureRefundRepository,
        private InitialStateIdLoader $initialStateIdLoader,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Handles the Paystack refund event data and initializes Shopware refund records.
     *
     * @param array<string, mixed> $data
     */
    public function handle(array $data, Context $context): void
    {
        $transactionReference = (string) ($data['transaction_reference'] ?? '');
        $paystackRefundId = (string) ($data['id'] ?? '');

        if (!$this->isValidRefundData($transactionReference, $paystackRefundId, $data)) {
            return;
        }

        $externalReference = $this->buildExternalReference($transactionReference, $paystackRefundId);

        $transaction = $this->findTransaction($transactionReference, $context);
        if ($transaction === null) {
            $this->logger->error('[Paystack] Could not find transaction for reference: ' . $transactionReference);
            return;
        }

        $refundAmount = $this->convertAmount((int) ($data['amount'] ?? 0));
        if (!$this->isValidRefundAmount($refundAmount, $transactionReference)) {
            return;
        }

        $captureId = $this->getOrCreateCapture($externalReference, $transaction, $refundAmount, $context);
        if ($captureId === '') {
            return;
        }

        if ($this->refundExists($externalReference, $context)) {
            $this->logger->info('[Paystack] Refund already exists: ' . $externalReference);
            return;
        }

        $this->createRefund($captureId, $externalReference, $refundAmount, $data, $context);
        $this->logger->info('[Paystack] Successfully initialized refund: ' . $externalReference);
    }

    /**
     * Validates the refund data has required fields.
     */
    private function isValidRefundData(string $transactionReference, string $paystackRefundId, array $data): bool
    {
        if ($transactionReference !== '' && $paystackRefundId !== '') {
            return true;
        }

        $this->logger->error('[Paystack] Invalid refund data: missing transaction_reference or id', [
            'data' => $data,
        ]);

        return false;
    }

    /**
     * Validates the refund amount is positive.
     */
    private function isValidRefundAmount(float $refundAmount, string $transactionReference): bool
    {
        if ($refundAmount > 0.0) {
            return true;
        }

        $this->logger->warning('[Paystack] Invalid refund amount: ' . $refundAmount, [
            'transactionReference' => $transactionReference,
        ]);

        return false;
    }

    /**
     * Converts kobo to naira safely.
     */
    private function convertAmount(int $amountInKobo): float
    {
        return round($amountInKobo / 100, 2);
    }

    /**
     * Finds the order transaction by the Paystack reference.
     */
    private function findTransaction(string $reference, Context $context): ?OrderTransactionEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter(
                sprintf('customFields.%s', PaystackConstants::FIELD_REFERENCE),
                $reference
            )
        );
        $criteria->addAssociation('captures');

        /** @var OrderTransactionEntity|null $transaction */
        $transaction = $this->orderTransactionRepository->search($criteria, $context)->first();
        return $transaction;
    }

    /**
     * Builds a unique external reference from transaction and refund IDs.
     */
    public static function buildExternalReference(string $paystackTransactionReference, string $paystackTransactionId): string
    {
        return $paystackTransactionId . '-'. $paystackTransactionReference;
    }

    /**
     * Retrieves an existing capture or creates a new one for the transaction.
     * Supports multiple captures per transaction (important for partial refunds).
     */
    private function getOrCreateCapture(
        string $externalReference,
        OrderTransactionEntity $transaction,
        float $captureAmount,
        Context $context
    ): string {
        $captureId = Uuid::fromStringToHex('paystack-capture-' . $externalReference);

        if ($this->entityExists($this->orderTransactionCaptureRepository, $captureId, $context)) {
            return $captureId;
        }

        $existingCaptureId = $this->findExistingCaptureInTransaction($externalReference, $transaction);
        if ($existingCaptureId !== null) {
            return $existingCaptureId;
        }

        return $this->createNewCapture($captureId, $externalReference, $transaction, $captureAmount, $context);
    }

    /**
     * Finds an existing capture in the transaction's captures.
     */
    private function findExistingCaptureInTransaction(string $externalReference, OrderTransactionEntity $transaction): ?string
    {
        $captures = $transaction->getCaptures();
        if ($captures === null || $captures->count() === 0) {
            return null;
        }

        foreach ($captures as $capture) {
            if ($capture->getExternalReference() === $externalReference) {
                return $capture->getId();
            }
        }

        return null;
    }

    /**
     * Creates a new capture record.
     */
    private function createNewCapture(
        string $captureId,
        string $externalReference,
        OrderTransactionEntity $transaction,
        float $captureAmount,
        Context $context
    ): string {
        try {
            $this->orderTransactionCaptureRepository->create([
                [
                    'id' => $captureId,
                    'orderTransactionId' => $transaction->getId(),
                    'externalReference' => $externalReference,
                    'stateId' => $this->initialStateIdLoader->get(OrderTransactionCaptureStates::STATE_MACHINE),
                    'amount' => $this->createPrice($captureAmount),
                ],
            ], $context);

            return $captureId;
        } catch (\Exception $e) {
            $this->logger->error('[Paystack] Failed to create capture', [
                'exception' => $e->getMessage(),
                'transactionId' => $transaction->getId(),
                'captureId' => $captureId,
            ]);

            return '';
        }
    }

    /**
     * Creates a CalculatedPrice object for the given amount.
     */
    private function createPrice(float $amount): CalculatedPrice
    {
        return new CalculatedPrice(
            $amount,
            $amount,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        );
    }

    /**
     * Checks if an entity exists with the given ID in the repository.
     */
    private function entityExists(EntityRepository $repository, string $id, Context $context): bool
    {
        $criteria = new Criteria([$id]);
        return $repository->searchIds($criteria, $context)->getTotal() > 0;
    }

    /**
     * Checks if a refund with the given external reference already exists.
     */
    private function refundExists(string $externalReference, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('externalReference', $externalReference));

        return $this->orderTransactionCaptureRefundRepository
                ->searchIds($criteria, $context)
                ->getTotal() > 0;
    }

    /**
     * Creates a new refund record.
     */
    private function createRefund(
        string $captureId,
        string $externalReference,
        float $refundAmount,
        array $data,
        Context $context
    ): void {
        $refundId = Uuid::fromStringToHex('paystack-refund-' . $externalReference);

        if ($this->entityExists($this->orderTransactionCaptureRefundRepository, $refundId, $context)) {
            $this->logger->info('[Paystack] Refund with ID already exists: ' . $refundId);
            return;
        }

        $this->orderTransactionCaptureRefundRepository->create([
            [
                'id' => $refundId,
                'captureId' => $captureId,
                'externalReference' => $externalReference,
                'stateId' => $this->initialStateIdLoader->get(OrderTransactionCaptureRefundStates::STATE_MACHINE),
                'amount' => $this->createPrice($refundAmount),
                'reason' => $data['merchant_note']
                    ?? $data['customer_note']
                        ?? 'Paystack refund',
            ],
        ], $context);
    }
}