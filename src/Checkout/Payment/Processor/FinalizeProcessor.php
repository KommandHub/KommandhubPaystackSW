<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Processor;

use Kommandhub\PaystackSW\Checkout\Payment\Enum\PaystackTransactionStatus;
use Kommandhub\PaystackSW\Service\Entity\OrderTransactionService;
use Kommandhub\PaystackSW\Service\PaymentFinalizedEventService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
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
     * @param LoggerInterface $logger
     */
    public function __construct(
        private OrderTransactionService $orderTransactionService,
        private OrderTransactionStateHandler $transactionStateHandler,
        private TransactionVerificationProcessorInterface $verificationProcessor,
        private TransactionMetadataProcessorInterface $metadataProcessor,
        private PaymentFinalizedEventService $paymentFinalizedEventService,
        private LoggerInterface $logger,
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
        Context $context
    ): void {
        $transactionId = $transaction->getOrderTransactionId();

        $orderTransaction = $this->orderTransactionService->readOneById($transactionId, $context);

        $reference = $this->extractReference($request, $transactionId);

        try {
            $verification = $this->verificationProcessor->verify(
                $reference,
                $orderTransaction,
                $context
            );
        } catch (\Throwable $e) {
            $this->logger->error('Paystack verification failed.', [
                'transaction_id' => $transactionId,
                'reference' => $reference,
                'exception' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->metadataProcessor->persist(
            $transactionId,
            $reference,
            $verification,
            $context
        );

        $statusValue = '';

        if (isset($verification['data']) && is_array($verification['data'])) {
            $rawStatus = $verification['data']['status'] ?? '';
            $statusValue = is_scalar($rawStatus) ? (string)$rawStatus : '';
        }

        if ($statusValue === PaystackTransactionStatus::SUCCESS->value) {
            if (!$this->isAlreadyPaid($orderTransaction)) {
                $this->transactionStateHandler->paid($transactionId, $context);
            }

            $this->dispatchFinalizedEvent($orderTransaction, $transaction, $context);
        }

        $paystackTransactionId = '';

        if (isset($verification['data']) && is_array($verification['data'])) {
            $rawId = $verification['data']['id'] ?? '';
            $paystackTransactionId = is_scalar($rawId) ? (string)$rawId : '';
        }

        $this->logger->info('Paystack payment finalized.', [
            'transaction_id' => $transactionId,
            'reference' => $reference,
            'paystack_transaction_id' => $paystackTransactionId,
            'status' => $statusValue,
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
            $this->logger->error('Missing Paystack reference.', [
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
            return;
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
    private function isAlreadyPaid(OrderTransactionEntity $transaction): bool
    {
        return $transaction->getStateMachineState()?->getTechnicalName() === 'paid';
    }
}
