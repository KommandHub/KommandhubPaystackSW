<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment;

use DateTimeImmutable;
use Kommandhub\PaystackSW\Service\Config;
use Kommandhub\PaystackSW\Exceptions\PaystackException;
use Kommandhub\PaystackSW\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Service\PayloadBuilder;
use Kommandhub\PaystackSW\Service\PaymentFinalizedEventService;
use Kommandhub\PaystackSW\Service\TransactionService;
use Kommandhub\PaystackSW\Util\PaystackConstants;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles Paystack payment initialization and finalization.
 */
final class PaystackPaymentHandler extends AbstractPaystackPaymentHandler
{
    public function __construct(
        private readonly OrderTransactionService $orderTransactionService,
        private readonly TransactionService $transactionService,
        private readonly PayloadBuilder $payloadBuilder,
        private readonly OrderTransactionStateHandler $transactionStateHandler,
        private readonly Config $config,
        private readonly PaymentFinalizedEventService $paymentFinalizedEventService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Initializes a Paystack payment session.
     *
     * @throws PaymentException
     */
    public function pay(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context,
        ?Struct $validateStruct
    ): RedirectResponse {
        $transactionId = $transaction->getOrderTransactionId();

        $orderTransaction = $this->orderTransactionService->get(
            $transactionId,
            $context
        );

        try {
            $payload = $this->payloadBuilder->build(
                $orderTransaction,
                $transaction
            );

            $response = $this->transactionService->initialize($payload);
        } catch (\RuntimeException $exception) {
            $this->logError(
                'Failed to build Paystack payment payload.',
                $transactionId,
                ['exception' => $exception]
            );

            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                sprintf(
                    'Unable to prepare payment payload: %s',
                    $exception->getMessage()
                )
            );
        } catch (PaystackException $exception) {
            $this->logError(
                'Paystack communication error during initialization.',
                $transactionId,
                ['exception' => $exception]
            );

            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                'A communication error occurred with the payment gateway'
            );
        }

        if (($response['status'] ?? false) !== true) {
            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                sprintf(
                    'Paystack declined to initialize the payment: %s',
                    $response['message'] ?? 'Unknown error'
                )
            );
        }

        $authorizationUrl = $response['data']['authorization_url'] ?? null;

        if (!is_string($authorizationUrl) || $authorizationUrl === '') {
            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                'Paystack did not return a checkout URL'
            );
        }

        $this->logInfo(
            'Paystack payment initialized successfully.',
            $transactionId,
            ['response' => $response]
        );

        return new RedirectResponse($authorizationUrl);
    }

    /**
     * Finalizes a Paystack payment.
     *
     * @throws PaystackException
     * @throws PaymentException
     */
    public function finalize(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
        $transactionId = $transaction->getOrderTransactionId();

        $orderTransaction = $this->orderTransactionService->get(
            $transactionId,
            $context
        );

        $reference = $this->extractReference(
            $request,
            $orderTransaction
        );

        $verification = $this->verifyTransaction(
            $reference,
            $orderTransaction,
            $context
        );

        $this->markTransactionAsPaid(
            $transaction,
            $context
        );

        $this->persistTransactionMetadata(
            $transactionId,
            $reference,
            $verification,
            $context
        );

        $this->dispatchFinalizedEvent(
            $orderTransaction,
            $transaction,
            $context
        );

        $data = $verification['data'] ?? [];
        $paystackTransactionId = null;

        if (is_array($data) && isset($data['id'])) {
            $paystackTransactionId = $data['id'];
        }

        $this->logInfo(
            'Paystack payment finalized successfully.',
            $transactionId,
            [
                'reference' => $reference,
                'paystack_transaction_id' => $paystackTransactionId,
            ]
        );
    }

    /**
     * Extracts and validates Paystack reference.
     */
    private function extractReference(
        Request $request,
        OrderTransactionEntity $transaction
    ): string {
        $reference = $request->query->getString('reference');

        if ($reference === '') {
            $this->logError(
                'Missing Paystack transaction reference.',
                $transaction->getId(),
                ['query' => $request->query->all()]
            );

            throw PaymentException::customerCanceled(
                $transaction->getId(),
                'Payment reference is missing from the request.'
            );
        }

        return $reference;
    }

    /**
     * Verifies transaction against Paystack API.
     *
     * @return array<string, mixed>
     *
     * @throws PaystackException
     * @throws PaymentException
     */
    private function verifyTransaction(
        string $reference,
        OrderTransactionEntity $transaction,
        Context $context
    ): array {
        $verification = $this->transactionService->verify($reference);

        if (($verification['status'] ?? false) !== true) {
            $this->transactionStateHandler->fail($transaction->getId(), $context);

            $this->logError(
                'Paystack payment verification failed.',
                $transaction->getId(),
                ['verification' => $verification]
            );

            throw PaymentException::asyncFinalizeInterrupted(
                $transaction->getId(),
                sprintf(
                    'Unable to verify payment: %s',
                    $verification['message'] ?? 'Unknown error'
                )
            );
        }

        return $verification;
    }

    /**
     * Marks Shopware transaction as paid.
     */
    private function markTransactionAsPaid(
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
        $this->transactionStateHandler->paid(
            $transaction->getOrderTransactionId(),
            $context
        );
    }

    /**
     * Persists Paystack metadata into transaction custom fields.
     *
     * @param array<string, mixed> $verification
     */
    private function persistTransactionMetadata(
        string $transactionId,
        string $reference,
        array $verification,
        Context $context
    ): void {
        $data = $verification['data'] ?? [];

        if (!is_array($data)) {
            $data = [];
        }

        $this->orderTransactionService->updateCustomFields(
            $transactionId,
            [
                PaystackConstants::FIELD_REFERENCE => $reference,
                PaystackConstants::FIELD_TRANSACTION_ID => $data['id'] ?? null,
                PaystackConstants::FIELD_PAYMENT_TYPE => $data['channel'] ?? null,
                PaystackConstants::FIELD_TRANSACTION_FEE => isset($data['fees']) && is_numeric($data['fees'])
                    ? ((float)$data['fees'] / 100)
                    : null,
                PaystackConstants::FIELD_AMOUNT => isset($data['amount']) && is_numeric($data['amount'])
                    ? ((float)$data['amount'] / 100)
                    : null,
                PaystackConstants::FIELD_CURRENCY => $data['currency'] ?? null,
                PaystackConstants::FIELD_VERIFIED_AT => (
                new DateTimeImmutable()
                )->format('Y-m-d H:i:s'),
            ],
            $context
        );
    }

    /**
     * Dispatches finalized payment event.
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
     * Retrieves the order transaction entity.
     *
     * @throws PaymentException
     */
    public function getOrderTransaction(string $transactionId, Context $context): OrderTransactionEntity
    {
        return $this->orderTransactionService->get($transactionId, $context);
    }

    /**
     * Logs informational messages when debugging is enabled.
     *
     * @param array<string, mixed> $contextData
     */
    private function logInfo(
        string $message,
        string $transactionId,
        array $contextData = []
    ): void {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $this->logger->info(
            $message,
            array_merge(
                ['transaction_id' => $transactionId],
                $contextData
            )
        );
    }

    /**
     * Logs errors when debugging is enabled.
     *
     * @param array<string, mixed> $contextData
     */
    private function logError(
        string $message,
        string $transactionId,
        array $contextData = []
    ): void {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $this->logger->error(
            $message,
            array_merge(
                ['transaction_id' => $transactionId],
                $contextData
            )
        );
    }

    /**
     * Determines whether debugging is enabled.
     */
    private function isDebugEnabled(): bool
    {
        return $this->config->getBool('enableDebugging', null);
    }
}
