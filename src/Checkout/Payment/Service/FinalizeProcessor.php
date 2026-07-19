<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Service;

use Kommandhub\PaystackSW\Checkout\Payment\Enum\PaystackTransactionStatus;
use Kommandhub\PaystackSW\Exception\PaymentException;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Symfony\Component\HttpFoundation\Request;

readonly class FinalizeProcessor
{
    /**
     * @param OrderTransactionService $orderTransactionService
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param TransactionVerificationProcessorInterface $verificationProcessor
     * @param TransactionMetadataProcessorInterface $metadataProcessor
     * @param PaymentFinalizedEventService $paymentFinalizedEventService
     * @param ConfigurableLogger $logger
     */
    public function __construct(
        private OrderTransactionService $orderTransactionService,
        private OrderTransactionStateHandler $transactionStateHandler,
        private TransactionVerificationProcessorInterface $verificationProcessor,
        private TransactionMetadataProcessorInterface $metadataProcessor,
        private PaymentFinalizedEventService $paymentFinalizedEventService,
        private ConfigurableLogger $logger,
    ) {
    }

    /**
     * Processes the finalization of a Paystack payment.
     *
     * @param Request $request
     * @param PaymentTransactionStruct $transaction
     * @param Context $context
     *
     * @throws PaymentException
     * @throws \Throwable
     */
    public function process(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?OrderTransactionEntity $orderTransaction = null
    ): void {
        $transactionId = $transaction->getOrderTransactionId();

        // Reuse a caller-supplied entity (e.g. the webhook listener already
        // loaded it) to avoid re-hydrating the full association graph.
        $orderTransaction ??= $this->orderTransactionService->readOneById($transactionId, $context);

        $reference = $this->extractReference($request, $transactionId);

        if ($this->isAlreadyProcessed($orderTransaction)) {
            $this->logger->info('[Paystack] Payment already processed.', [
                ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $orderTransaction->getOrder()?->getSalesChannelId(),
                'transaction_id' => $transactionId,
                'reference' => $reference,
            ]);

            return;
        }

        $verification = $this->verificationProcessor->verify(
            $reference,
            $orderTransaction,
            $context
        );

        if (!$this->isSuccessfulVerification($verification)) {
            return;
        }

        $this->metadataProcessor->persist(
            $transactionId,
            $reference,
            $verification,
            $context
        );

        $this->transactionStateHandler->paid($transactionId, $context);

        $this->dispatchFinalizedEvent($orderTransaction, $transaction, $context);

        $data = $verification['data'] ?? [];
        $paystackTransactionId = '';

        if (is_array($data)) {
            $id = $data['id'] ?? '';
            $paystackTransactionId = is_scalar($id) ? (string)$id : '';
        }

        $this->logger->info('[Paystack] Payment finalized.', [
            ConfigurableLogger::CONTEXT_SALES_CHANNEL_ID => $orderTransaction->getOrder()?->getSalesChannelId(),
            'transaction_id' => $transactionId,
            'reference' => $reference,
            'paystack_transaction_id' => $paystackTransactionId,
            'status' => PaystackTransactionStatus::SUCCESS->value,
        ]);
    }

    /**
     * Extracts the Paystack reference from the request.
     *
     * @param Request $request
     * @param string $transactionId
     *
     * @return string
     *
     * @throws PaymentException
     */
    private function extractReference(Request $request, string $transactionId): string
    {
        $reference = $request->query->getString('reference');

        if ($reference === '') {
            $this->logger->error('[Paystack] Missing transaction reference.', [
                'transaction_id' => $transactionId,
                'query' => $request->query->all(),
            ]);

            throw PaymentException::customerCanceled(
                $transactionId,
                'Payment reference is missing from request.'
            );
        }

        return $reference;
    }

    /**
     * Dispatches the payment finalized event.
     *
     * @param OrderTransactionEntity $orderTransaction
     * @param PaymentTransactionStruct $transaction
     * @param Context $context
     */
    private function dispatchFinalizedEvent(
        OrderTransactionEntity $orderTransaction,
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
        $order = $orderTransaction->getOrder();

        if (!$order instanceof OrderEntity) {
            return; // @codeCoverageIgnore
        }

        $this->paymentFinalizedEventService->fireEvent(
            $order,
            $orderTransaction,
            $transaction,
            $context
        );
    }

    /**
     * Checks if the transaction is already marked as paid.
     *
     * @param OrderTransactionEntity $transaction
     *
     * @return bool
     */
    private function isAlreadyProcessed(OrderTransactionEntity $transaction): bool
    {
        return $transaction->getStateMachineState()?->getTechnicalName() === 'paid';
    }

    /**
     * @param array<string, mixed> $verification
     */
    private function isSuccessfulVerification(array $verification): bool
    {
        return $this->getVerificationStatus($verification) === PaystackTransactionStatus::SUCCESS->value;
    }

    /**
     * @param array<string, mixed> $verification
     */
    private function getVerificationStatus(array $verification): string
    {
        $data = $verification['data'] ?? [];

        if (!is_array($data)) {
            return ''; // @codeCoverageIgnore
        }

        $status = $data['status'] ?? '';

        return is_scalar($status) ? (string)$status : '';
    }
}
