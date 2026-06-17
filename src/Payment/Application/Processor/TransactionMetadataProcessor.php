<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Payment\Application\Processor;

use DateTimeImmutable;
use Kommandhub\PaystackSW\Payment\Application\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Core\Util\PaystackConstants;
use Kommandhub\PaystackSW\Core\Util\PaystackCurrencyHelper;
use Shopware\Core\Framework\Context;

class TransactionMetadataProcessor implements TransactionMetadataProcessorInterface
{
    /**
     * @param OrderTransactionService $orderTransactionService
     */
    public function __construct(
        private readonly OrderTransactionService $orderTransactionService,
    ) {
    }

    /**
     * Persists Paystack transaction metadata to the Shopware order transaction.
     *
     * @param string $transactionId
     * @param string $reference
     * @param array $verificationData
     * @param Context $context
     */
    public function persist(string $transactionId, string $reference, array $verificationData, Context $context): void
    {
        $data = $verificationData['data'] ?? [];

        if (!is_array($data)) {
            $data = []; // @codeCoverageIgnore
        }

        $this->orderTransactionService->updateCustomFields(
            $transactionId,
            [
                PaystackConstants::FIELD_REFERENCE => $reference,
                PaystackConstants::FIELD_TRANSACTION_ID => $data['id'] ?? null,
                PaystackConstants::FIELD_PAYMENT_TYPE => $data['channel'] ?? null,
                PaystackConstants::FIELD_TRANSACTION_FEE => isset($data['fees']) && is_numeric($data['fees'])
                    ? PaystackCurrencyHelper::fromMinorUnit(
                        (int)$data['fees'],
                        is_scalar($data['currency'] ?? null) ? (string)$data['currency'] : 'NGN'
                    )
                    : null,
                PaystackConstants::FIELD_AMOUNT => isset($data['amount']) && is_numeric($data['amount'])
                    ? PaystackCurrencyHelper::fromMinorUnit(
                        (int)$data['amount'],
                        is_scalar($data['currency'] ?? null) ? (string)$data['currency'] : 'NGN'
                    )
                    : null,
                PaystackConstants::FIELD_CURRENCY => $data['currency'] ?? null,
                PaystackConstants::FIELD_VERIFIED_AT => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ],
            $context
        );
    }
}
