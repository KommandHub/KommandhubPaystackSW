<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Checkout\Payment\Processor;

use Kommandhub\PaystackSW\Checkout\Payment\Processor\TransactionMetadataProcessor;
use Kommandhub\PaystackSW\Service\OrderTransactionService;
use Kommandhub\PaystackSW\Util\PaystackConstants;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;

#[CoversClass(TransactionMetadataProcessor::class)]
class TransactionMetadataProcessorTest extends TestCase
{
    private OrderTransactionService&MockObject $orderTransactionService;
    private Context $context;
    private TransactionMetadataProcessor $processor;

    protected function setUp(): void
    {
        $this->orderTransactionService = $this->createMock(OrderTransactionService::class);
        $this->context = Context::createDefaultContext();
        $this->processor = new TransactionMetadataProcessor($this->orderTransactionService);
    }

    public function testPersistSuccess(): void
    {
        $transactionId = 'test-transaction-id';
        $reference = 'test-reference';
        $verificationData = [
            'data' => [
                'id' => 'paystack-123',
                'channel' => 'card',
                'fees' => 150,
                'amount' => 10000,
                'currency' => 'NGN',
            ],
        ];

        $this->orderTransactionService->expects($this->once())
            ->method('updateCustomFields')
            ->with(
                $transactionId,
                $this->callback(function (array $fields) use ($reference) {
                    return $fields[PaystackConstants::FIELD_REFERENCE] === $reference
                        && $fields[PaystackConstants::FIELD_TRANSACTION_ID] === 'paystack-123'
                        && $fields[PaystackConstants::FIELD_PAYMENT_TYPE] === 'card'
                        && $fields[PaystackConstants::FIELD_TRANSACTION_FEE] === 1.5
                        && $fields[PaystackConstants::FIELD_AMOUNT] === 100.0
                        && $fields[PaystackConstants::FIELD_CURRENCY] === 'NGN'
                        && isset($fields[PaystackConstants::FIELD_VERIFIED_AT]);
                }),
                $this->context
            );

        $this->processor->persist($transactionId, $reference, $verificationData, $this->context);
    }

    public function testPersistWithMissingData(): void
    {
        $transactionId = 'test-transaction-id';
        $reference = 'test-reference';
        $verificationData = []; // Missing 'data' key

        $this->orderTransactionService->expects($this->once())
            ->method('updateCustomFields')
            ->with(
                $transactionId,
                $this->callback(function (array $fields) use ($reference) {
                    return $fields[PaystackConstants::FIELD_REFERENCE] === $reference
                        && $fields[PaystackConstants::FIELD_TRANSACTION_ID] === null
                        && $fields[PaystackConstants::FIELD_TRANSACTION_FEE] === null;
                }),
                $this->context
            );

        $this->processor->persist($transactionId, $reference, $verificationData, $this->context);
    }
}
