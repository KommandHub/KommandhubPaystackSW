<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Service;

use Kommandhub\PaystackSW\Checkout\Payment\Struct\PaystackInitializationResponse;
use Kommandhub\PaystackSW\Exception\PaymentException;
use Kommandhub\PaystackSW\Exception\PaystackException;
use Kommandhub\PaystackSW\Checkout\Payment\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Checkout\Payment\Service\PayloadBuilder;
use Kommandhub\PaystackSW\Checkout\Payment\Service\TransactionService;
use Kommandhub\PaystackSW\Util\PaystackConstants;
use Kommandhub\PaystackSW\Logging\ConfigurableLogger;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;

readonly class PaymentProcessor
{
    /**
     * @param OrderTransactionService $orderTransactionService
     * @param PayloadBuilder $payloadBuilder
     * @param TransactionService $transactionService
     * @param ConfigurableLogger $logger
     */
    public function __construct(
        private OrderTransactionService $orderTransactionService,
        private PayloadBuilder $payloadBuilder,
        private TransactionService $transactionService,
        private ConfigurableLogger $logger,
    ) {
    }

    /**
     * Processes the initialization of a Paystack payment.
     *
     * @param PaymentTransactionStruct $transaction
     * @param Context $context
     *
     * @return PaystackInitializationResponse
     *
     * @throws PaymentException
     */
    public function process(
        PaymentTransactionStruct $transaction,
        Context $context
    ): PaystackInitializationResponse {
        $transactionId = $transaction->getOrderTransactionId();
        $orderTransaction = $this->orderTransactionService->readOneById($transactionId, $context);

        try {
            $payload = $this->payloadBuilder->build($orderTransaction, $transaction);
            $response = $this->transactionService->initialize($payload);
        } catch (\RuntimeException $exception) {
            $this->logger->error('Failed to build Paystack payment payload.', [
                'transactionId' => $transactionId,
                'exception' => $exception,
            ]);

            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                sprintf('Unable to prepare payment payload: %s', $exception->getMessage())
            );
        } catch (PaystackException $exception) {
            $this->logger->error('Paystack communication error during initialization.', [
                'transactionId' => $transactionId,
                'exception' => $exception,
            ]);

            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                'A communication error occurred with the payment gateway'
            );
        }

        if (($response['status'] ?? false) !== true) {
            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                sprintf('Paystack declined to initialize the payment: %s', $response['message'] ?? 'Unknown error')
            );
        }

        $data = $response['data'] ?? [];
        $authorizationUrl = $data['authorization_url'] ?? null;
        $reference = $data['reference'] ?? null;

        if (!is_string($authorizationUrl) || $authorizationUrl === '' || !is_string($reference)) {
            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                'Paystack did not return a valid checkout URL or reference'
            );
        }

        // Persist the reference now so an abandoned redirect can still be
        // reconciled by the charge.success webhook (which looks the transaction
        // up by this custom field). Finalize later overwrites it with full data.
        $this->orderTransactionService->updateCustomFields(
            $transactionId,
            [PaystackConstants::FIELD_REFERENCE => $reference],
            $context
        );

        return new PaystackInitializationResponse(
            $authorizationUrl,
            $reference,
            $data['access_code'] ?? null
        );
    }
}
