<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Processor;

use Kommandhub\PaystackSW\Checkout\Payment\Enum\PaystackTransactionStatus;
use Kommandhub\PaystackSW\Exceptions\PaymentException;
use Kommandhub\PaystackSW\Service\TransactionService;
use Kommandhub\PaystackSW\Util\PaystackCurrencyHelper;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Framework\Context;

readonly class TransactionVerificationProcessor implements TransactionVerificationProcessorInterface
{
    public function __construct(
        private TransactionService $transactionService,
        private OrderTransactionStateHandler $transactionStateHandler,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @throws PaymentException
     */
    public function verify(
        string $reference,
        OrderTransactionEntity $transaction,
        Context $context
    ): array {
        $verification = $this->fetchVerification($reference, $transaction);

        $this->assertSuccessfulResponse($verification, $transaction);

        $data = $this->getData($verification, $transaction);

        $this->assertTransactionAmount($data, $transaction);

        $this->assertTransactionStatus($data, $transaction, $context);

        return $verification;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws PaymentException
     */
    private function fetchVerification(
        string $reference,
        OrderTransactionEntity $transaction
    ): array {
        try {
            return $this->transactionService->verify($reference);
        } catch (\Throwable $e) {
            $this->logger->error('Paystack verification API failure', [
                'transactionId' => $transaction->getId(),
                'reference' => $reference,
                'error' => $e->getMessage(),
            ]);

            throw PaymentException::asyncFinalizeInterrupted(
                $transaction->getId(),
                'Payment verification temporarily unavailable.'
            );
        }
    }

    /**
     * @param array<string, mixed> $verification
     */
    private function assertSuccessfulResponse(
        array $verification,
        OrderTransactionEntity $transaction
    ): void {
        if (($verification['status'] ?? false) !== true) {
            $this->fail(
                $transaction->getId(),
                is_scalar($verification['message'] ?? null) ? (string)$verification['message'] : 'Invalid Paystack verification response.'
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getData(
        array $verification,
        OrderTransactionEntity $transaction
    ): array {
        $data = $verification['data'] ?? null;

        if (!is_array($data)) {
            $this->fail($transaction->getId(), 'Missing Paystack transaction data.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertTransactionAmount(
        array $data,
        OrderTransactionEntity $transaction
    ): void {
        $paystackAmount = $data['amount'] ?? null;

        if (!is_numeric($paystackAmount)) {
            $this->fail($transaction->getId(), 'Invalid or missing Paystack amount.');
        }

        $order = $transaction->getOrder();

        if ($order === null || $order->getCurrency() === null) {
            $this->fail($transaction->getId(), 'Missing order currency information.');
        }

        $expected = PaystackCurrencyHelper::toMinorUnit(
            $transaction->getAmount()->getTotalPrice(),
            $order->getCurrency()->getIsoCode()
        );

        if ((int)$paystackAmount !== $expected) {
            $this->logger->warning('Paystack amount mismatch', [
                'transactionId' => $transaction->getId(),
                'expected' => $expected,
                'received' => (int)$paystackAmount,
            ]);

            $this->fail(
                $transaction->getId(),
                'Payment amount mismatch detected.'
            );
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertTransactionStatus(
        array $data,
        OrderTransactionEntity $transaction,
        Context $context
    ): void {
        $statusValue = $data['status'] ?? null;

        if (!is_string($statusValue)) {
            $this->fail($transaction->getId(), 'Missing Paystack transaction status.');
        }

        $status = PaystackTransactionStatus::tryFrom($statusValue);

        if ($status === null) {
            $this->fail(
                $transaction->getId(),
                sprintf('Unknown Paystack status: %s', $statusValue)
            );
        }

        match ($status) {
            PaystackTransactionStatus::SUCCESS => null,

            PaystackTransactionStatus::ABANDONED => $this->fail($transaction->getId(), 'Payment was abandoned by the customer.'),

            PaystackTransactionStatus::FAILED,
            PaystackTransactionStatus::REVERSED => $this->fail($transaction->getId(), 'Payment was rejected by the bank.'),

            PaystackTransactionStatus::ONGOING,
            PaystackTransactionStatus::PENDING,
            PaystackTransactionStatus::PROCESSING,
            PaystackTransactionStatus::QUEUED => $this->handlePending($transaction->getId(), $context),
        };
    }

    /**
     * @throws PaymentException
     */
    private function handlePending(string $transactionId, Context $context): void
    {
        $this->transactionStateHandler->process($transactionId, $context);

        throw PaymentException::paymentVerificationPending();
    }

    /**
     * @throws PaymentException
     */
    private function fail(string $transactionId, string $message): never
    {
        throw PaymentException::asyncFinalizeInterrupted($transactionId, $message);
    }
}
