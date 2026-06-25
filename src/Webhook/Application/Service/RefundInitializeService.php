<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Webhook\Application\Service;

use Kommandhub\PaystackSW\Core\Util\PaystackConstants;
use Kommandhub\PaystackSW\Core\Util\PaystackCurrencyHelper;
use Kommandhub\PaystackSW\Core\Logging\ConfigurableLogger;
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
readonly class RefundInitializeService
{
    public function __construct(
        private EntityRepository $orderTransactionRepository,
        private EntityRepository $orderTransactionCaptureRepository,
        private EntityRepository $orderTransactionCaptureRefundRepository,
        private InitialStateIdLoader $initialStateIdLoader,
        private ConfigurableLogger $logger,
    ) {
    }

    /**
     * Handles the Paystack refund event data and initializes Shopware refund records.
     *
     * @param array<string, mixed> $data
     */
    public function handle(array $data, Context $context): void
    {
        $transactionReference = isset($data['transaction_reference']) && is_scalar($data['transaction_reference']) ? (string)$data['transaction_reference'] : '';
        $paystackRefundId = isset($data['id']) && is_scalar($data['id']) ? (string)$data['id'] : '';

        if (!$this->isValidRefundData($transactionReference, $paystackRefundId, $data)) {
            return;
        }

        $externalReference = $this->buildExternalReference($transactionReference, $paystackRefundId);

        $transaction = $this->findTransaction($transactionReference, $context);

        if ($transaction === null) {
            $this->logger->error('[Paystack] Could not find transaction for reference: ' . $transactionReference);

            return;
        }

        $rawAmount = $data['amount'] ?? 0;
        $currencyCode = $transaction->getOrder()?->getCurrency()?->getIsoCode() ?? 'NGN';
        $refundAmount = PaystackCurrencyHelper::fromMinorUnit(
            is_numeric($rawAmount) ? (int)$rawAmount : 0,
            $currencyCode
        );

        if (!$this->isValidRefundAmount($refundAmount, $transactionReference)) {
            return;
        }

        $captureId = $this->getOrCreateCapture($externalReference, $transaction, $refundAmount, $context);

        if ($captureId === '') {
            return; // @codeCoverageIgnore
        }

        if ($this->refundExists($externalReference, $context)) {
            $this->logger->info('[Paystack] Refund already exists: ' . $externalReference);

            return;
        }

        if (!$this->createRefund($captureId, $externalReference, $refundAmount, $data, $context)) {
            return;
        }
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
        return $paystackTransactionId . '-' . $paystackTransactionReference;
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

        return null; // @codeCoverageIgnore
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
        } catch (\Throwable $exception) {
            $this->logger->error('[Paystack] Failed to create capture', [
                'exception' => $exception,
                'transactionId' => $transaction->getId(),
                'captureId' => $captureId,
            ]);

            throw new \RuntimeException(
                sprintf('Failed to create capture for transaction "%s".', $transaction->getId()),
                0,
                $exception
            );
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
    ): bool {
        $refundId = Uuid::fromStringToHex('paystack-refund-' . $externalReference);

        if ($this->entityExists($this->orderTransactionCaptureRefundRepository, $refundId, $context)) {
            $this->logger->info('[Paystack] Refund with ID already exists: ' . $refundId);

            return false;
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

        return true;
    }
}
