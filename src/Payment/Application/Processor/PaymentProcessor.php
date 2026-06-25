<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Application\Processor;

use Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\Struct\PaystackInitializationResponse;
use Kommandhub\PaystackSW\Core\Exception\PaymentException;
use Kommandhub\PaystackSW\Core\Exception\PaystackException;
use Kommandhub\PaystackSW\Payment\Application\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Payment\Application\Service\PayloadBuilder;
use Kommandhub\PaystackSW\Payment\Application\Service\TransactionService;
use Kommandhub\PaystackSW\Core\Logging\ConfigurableLogger;
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

        return new PaystackInitializationResponse(
            $authorizationUrl,
            $reference,
            $data['access_code'] ?? null
        );
    }
}
