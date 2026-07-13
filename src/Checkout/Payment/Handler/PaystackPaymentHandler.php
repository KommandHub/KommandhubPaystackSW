<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Handler;

use Kommandhub\PaystackSW\Checkout\Payment\Service\FinalizeProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Service\PaymentProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Service\RefundProcessor;
use Kommandhub\PaystackSW\Checkout\Payment\Service\OrderTransactionService;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
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
        private readonly PaymentProcessor $paymentProcessor,
        private readonly FinalizeProcessor $finalizeProcessor,
        private readonly RefundProcessor $refundProcessor,
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
        $response = $this->paymentProcessor->process($transaction, $context);

        return new RedirectResponse($response->getAuthorizationUrl());
    }

    /**
     * Finalizes a Paystack payment.
     *
     * @throws PaymentException
     * @throws \Throwable
     */
    public function finalize(
        Request $request,
        PaymentTransactionStruct $transaction,
        Context $context
    ): void {
        $this->finalizeProcessor->process($request, $transaction, $context);
    }

    /**
     * @param RefundPaymentTransactionStruct $transaction
     * @param Context $context
     *
     * @return void
     *
     * @throws \Throwable
     */
    public function refund(RefundPaymentTransactionStruct $transaction, Context $context): void
    {
        $this->refundProcessor->process($transaction, $context);
    }

    /**
     * Retrieves the order transaction entity.
     *
     * @throws PaymentException
     */
    public function getOrderTransaction(string $transactionId, Context $context): OrderTransactionEntity
    {
        return $this->orderTransactionService->readOneById($transactionId, $context);
    }
}
