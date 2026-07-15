<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Checkout\Payment\Service;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;

interface TransactionVerificationProcessorInterface
{
    /**
     * @param string $reference
     * @param OrderTransactionEntity $transaction
     * @param Context $context
     *
     * @return array<string, mixed>
     */
    public function verify(string $reference, OrderTransactionEntity $transaction, Context $context): array;
}
