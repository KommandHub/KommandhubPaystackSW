<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Service\Entity;

use Kommandhub\Foundation\EntityHandler\OrderTransaction\OrderTransactionReader;
use Kommandhub\Foundation\EntityHandler\OrderTransaction\OrderTransactionWriter;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Checkout\Payment\PaymentException;

readonly class OrderTransactionService
{
    public function __construct(
        private OrderTransactionReader $orderTransactionReader,
        private OrderTransactionWriter $orderTransactionWriter,
    ) {
    }

    /**
     * @param string $transactionId
     * @param Context $context
     *
     * @return OrderTransactionEntity
     */
    public function readOneById(string $transactionId, Context $context): OrderTransactionEntity
    {
        $orderTransaction = $this->orderTransactionReader->readOneById(
            $transactionId,
            $context,
            $this->getCriteria()
        );

        if (!$orderTransaction instanceof OrderTransactionEntity) {
            throw PaymentException::asyncProcessInterrupted(
                $transactionId,
                sprintf('Order transaction "%s" could not be found.', $transactionId)
            );
        }

        return $orderTransaction;
    }

    /**
     * @param string $transactionId
     * @param array $customFields
     * @param Context $context
     */
    public function updateCustomFields(string $transactionId, array $customFields, Context $context): void
    {
        $this->orderTransactionWriter->write([
            'id' => $transactionId,
            'customFields' => $customFields,
        ], $context);
    }

    private function getCriteria(): Criteria
    {
        $criteria = new Criteria();
        $criteria->addAssociations([
            'order.currency',
            'order.lineItems',
            'order.orderCustomer.salutation',
            'order.billingAddress.country',
            'order.deliveries.shippingOrderAddress.country',
            'captures.refunds',
            'captures.stateMachineState',
            'captures.refunds.stateMachineState',
        ]);

        return $criteria;
    }
}
