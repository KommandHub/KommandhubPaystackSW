<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Event;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ShopwareEvent;

class PaymentFinalizedEvent implements ShopwareEvent
{
    public function __construct(
        protected OrderEntity $order,
        protected OrderTransactionEntity $orderTransaction,
        protected PaymentTransactionStruct $paymentTransactionStruct,
        protected Context $context,
    ) {
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getOrderTransaction(): OrderTransactionEntity
    {
        return $this->orderTransaction;
    }

    public function getPaymentTransactionStruct(): PaymentTransactionStruct
    {
        return $this->paymentTransactionStruct;
    }
}
