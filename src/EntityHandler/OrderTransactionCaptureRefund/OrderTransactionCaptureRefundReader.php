<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\EntityHandler\OrderTransactionCaptureRefund;

use Kommandhub\Foundation\EntityHandler\AbstractEntityReader;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

class OrderTransactionCaptureRefundReader extends AbstractEntityReader
{
    public function __construct(EntityRepository $orderTransactionCaptureRefundRepository)
    {
        parent::__construct($orderTransactionCaptureRefundRepository);
    }

    protected function getRepository(): EntityRepository
    {
        return $this->repository;
    }

    /**
     * Reads the ID of one matching result based on the provided criteria.
     *
     * @param Criteria $criteria The criteria to filter the search.
     * @param Context $context The context in which the search is executed.
     *
     * @return string|null The ID of the first matching result, or null if no result is found.
     */
    public function readIdOfOne(Criteria $criteria, Context $context): ?string
    {
        return $this->repository
            ->searchIds(
                $this->criteria($criteria),
                $context
            )
            ->firstId();
    }
}
