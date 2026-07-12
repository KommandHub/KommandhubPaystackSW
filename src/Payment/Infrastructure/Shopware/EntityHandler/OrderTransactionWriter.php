<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\EntityHandler;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * Concrete writer for Order Transaction entities.
 *
 * @extends AbstractEntityWriter<\Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionCollection>
 */
class OrderTransactionWriter extends AbstractEntityWriter
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
