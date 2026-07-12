<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Application\Service;

use Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\EntityHandler\OrderTransactionReader;
use Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\EntityHandler\OrderTransactionWriter;
use Kommandhub\PaystackSW\Core\Util\PaystackConstants;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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
     * @param Criteria $criteria
     * @param Context $context
     *
     * @return OrderTransactionCollection
     */
    public function search(Criteria $criteria, Context $context): OrderTransactionCollection
    {
        /** @var OrderTransactionCollection $transactions */
        $transactions = $this->orderTransactionReader->readAll($context, $criteria);

        return $transactions;
    }

    public function findOneByPaystackReference(string $reference, Context $context): ?OrderTransactionEntity
    {
        $criteria = $this->getCriteria();
        $criteria->addFilter(
            new EqualsFilter(
                sprintf('customFields.%s', PaystackConstants::FIELD_REFERENCE),
                $reference
            )
        );

        /** @var OrderTransactionEntity|null $orderTransaction */
        $orderTransaction = $this->search($criteria, $context)->first();

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
            'stateMachineState',
            'captures.refunds',
            'captures.stateMachineState',
            'captures.refunds.stateMachineState',
        ]);

        return $criteria;
    }
}
