<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Application\Service;

use Kommandhub\PaystackSW\Payment\Domain\Event\PaymentFinalizedEvent;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Service responsible for handling the dispatching of the Paystack payment finalized event.
 * This class is designed to ensure that the corresponding event is triggered when a Paystack payment is finalized.
 *
 * The `fireEvent` method dispatches the PaystackPaymentFinalizedEvent with all necessary data
 * such as order details, transaction details, and contextual information.
 */
readonly class PaymentFinalizedEventService
{
    public function __construct(private EventDispatcherInterface $eventDispatcher)
    {
    }

    /**
     * Dispatches the PaystackPaymentFinalizedEvent.
     *
     * @param OrderEntity $order The order entity related to the event.
     * @param OrderTransactionEntity $orderTransaction The order transaction entity associated with the event.
     * @param PaymentTransactionStruct $paymentTransactionStruct The payment transaction details for the event.
     * @param Context $context The context in which the event is dispatched.
     *
     * @return void
     */
    public function fireEvent(
        OrderEntity $order,
        OrderTransactionEntity $orderTransaction,
        PaymentTransactionStruct $paymentTransactionStruct,
        Context $context
    ): void {
        $this->eventDispatcher->dispatch(
            new PaymentFinalizedEvent($order, $orderTransaction, $paymentTransactionStruct, $context)
        );
    }
}
