<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Processor;

use Kommandhub\PaystackSW\Checkout\Payment\Enum\PaystackTransactionStatus;
use Kommandhub\PaystackSW\Service\TransactionService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;

class TransactionVerificationProcessor implements TransactionVerificationProcessorInterface
{
    /**
     * @param TransactionService $transactionService
     * @param OrderTransactionStateHandler $transactionStateHandler
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly TransactionService $transactionService,
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Verifies the Paystack transaction status.
     *
     * @param string $reference
     * @param OrderTransactionEntity $transaction
     * @param Context $context
     *
     * @return array
     *
     * @throws PaymentException
     */
    public function verify(string $reference, OrderTransactionEntity $transaction, Context $context): array
    {
        try {
            $verification = $this->transactionService->verify($reference);
        } catch (\Throwable $e) {
            $this->logger->error('Paystack API error during verification.', [
                'transaction_id' => $transaction->getId(),
                'reference' => $reference,
                'exception' => $e->getMessage(),
            ]);

            $this->transactionStateHandler->process($transaction->getId(), $context);

            throw PaymentException::asyncFinalizeInterrupted(
                $transaction->getId(),
                'Payment verification temporarily failed.'
            );
        }

        if (($verification['status'] ?? false) !== true) {
            $this->transactionStateHandler->fail($transaction->getId(), $context);

            throw PaymentException::asyncFinalizeInterrupted(
                $transaction->getId(),
                $verification['message'] ?? 'Unable to verify Paystack payment.'
            );
        }

        $this->assertTransactionStatus($verification, $transaction, $context);

        return $verification;
    }

    /**
     * Asserts the Paystack transaction status and transitions the order transaction state accordingly.
     *
     * @param array $verification
     * @param OrderTransactionEntity $transaction
     * @param Context $context
     */
    private function assertTransactionStatus(
        array $verification,
        OrderTransactionEntity $transaction,
        Context $context
    ): void {
        $statusValue = $verification['data']['status'] ?? null;

        if ($statusValue === null) { // @codeCoverageIgnoreStart
            $this->transactionStateHandler->fail($transaction->getId(), $context);

            throw PaymentException::asyncFinalizeInterrupted(
                $transaction->getId(),
                'Missing Paystack transaction status.'
            );
        } // @codeCoverageIgnoreEnd

        $status = PaystackTransactionStatus::tryFrom((string)$statusValue);

        match ($status) {
            PaystackTransactionStatus::SUCCESS => null,

            PaystackTransactionStatus::ABANDONED => $this->handleCancel(
                $transaction->getId(),
                $context,
                'The customer abandoned the payment.'
            ),

            PaystackTransactionStatus::FAILED,
            PaystackTransactionStatus::REVERSED => $this->handleFail(
                $transaction->getId(),
                $context,
                'The payment failed or was reversed.'
            ),

            PaystackTransactionStatus::ONGOING,
            PaystackTransactionStatus::PENDING,
            PaystackTransactionStatus::PROCESSING,
            PaystackTransactionStatus::QUEUED => $this->handleProcess(
                $transaction->getId(),
                $context
            ),

            // @codeCoverageIgnoreStart
            default => throw PaymentException::asyncFinalizeInterrupted(
                $transaction->getId(),
                sprintf('Unknown Paystack status: %s', $statusValue)
            ),
            // @codeCoverageIgnoreEnd
        };
    }

    /**
     * Handles the cancellation of a payment.
     *
     * @param string $transactionId
     * @param Context $context
     * @param string $message
     *
     * @throws PaymentException
     */
    private function handleCancel(string $transactionId, Context $context, string $message): void
    {
        $this->transactionStateHandler->cancel($transactionId, $context);

        throw PaymentException::customerCanceled($transactionId, $message);
    }

    /**
     * Handles the failure of a payment.
     *
     * @param string $transactionId
     * @param Context $context
     * @param string $message
     *
     * @throws PaymentException
     */
    private function handleFail(string $transactionId, Context $context, string $message): void
    {
        $this->transactionStateHandler->fail($transactionId, $context);

        throw PaymentException::asyncFinalizeInterrupted($transactionId, $message);
    }

    /**
     * Transitions the transaction to the "process" state for intermediate statuses.
     *
     * @param string $transactionId
     * @param Context $context
     */
    private function handleProcess(string $transactionId, Context $context): void
    {
        $this->transactionStateHandler->process($transactionId, $context);
    }
}
