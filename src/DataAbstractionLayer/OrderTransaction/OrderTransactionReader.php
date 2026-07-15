<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\DataAbstractionLayer\OrderTransaction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Concrete reader for Order Transaction entities.
 *
 * @extends AbstractEntityReader<\Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity, \Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection>
 */
class OrderTransactionReader extends AbstractEntityReader
{
    /**
     * @param EntityRepository<\Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection> $orderTransactionRepository
     */
    public function __construct(
        EntityRepository $orderTransactionRepository
    ) {
        parent::__construct($orderTransactionRepository);
    }

    /**
     * Returns the Order Transaction repository.
     *
     * @return EntityRepository<\Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection>
     */
    protected function getRepository(): EntityRepository
    {
        return $this->repository;
    }
}
