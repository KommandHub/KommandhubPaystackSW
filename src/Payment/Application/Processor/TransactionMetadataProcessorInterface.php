<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Application\Processor;

use Shopware\Core\Framework\Context;

interface TransactionMetadataProcessorInterface
{
    /**
     * Persists Paystack transaction metadata to the Shopware order transaction.
     *
     * @param string $transactionId
     * @param string $reference
     * @param array<string, mixed> $verificationData
     * @param Context $context
     */
    public function persist(string $transactionId, string $reference, array $verificationData, Context $context): void;
}
