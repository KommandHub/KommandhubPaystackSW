<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Service;

use Kommandhub\Foundation\EntityHandler\OrderTransaction\OrderTransactionReader;
use Kommandhub\Foundation\EntityHandler\OrderTransaction\OrderTransactionWriter;
use Kommandhub\PaystackSW\Service\OrderTransactionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;

#[CoversClass(OrderTransactionService::class)]
class OrderTransactionServiceTest extends TestCase
{
    private OrderTransactionReader $reader;
    private OrderTransactionWriter $writer;
    private OrderTransactionService $service;

    protected function setUp(): void
    {
        $this->reader = $this->createMock(OrderTransactionReader::class);
        $this->writer = $this->createMock(OrderTransactionWriter::class);
        $this->service = new OrderTransactionService($this->reader, $this->writer);
    }

    public function testGetSuccessful(): void
    {
        $transactionId = 'test-id';
        $context = Context::createDefaultContext();
        $orderTransaction = $this->createMock(OrderTransactionEntity::class);

        $this->reader->expects($this->once())
            ->method('readOneById')
            ->with($transactionId, $context, $this->isInstanceOf(Criteria::class))
            ->willReturn($orderTransaction);

        $result = $this->service->get($transactionId, $context);

        $this->assertSame($orderTransaction, $result);
    }

    public function testGetThrowsExceptionWhenNotFound(): void
    {
        $transactionId = 'test-id';
        $context = Context::createDefaultContext();

        $this->reader->method('readOneById')->willReturn(null);

        $this->expectException(PaymentException::class);
        $this->expectExceptionMessage(sprintf('Order transaction "%s" could not be found.', $transactionId));

        $this->service->get($transactionId, $context);
    }

    public function testUpdateCustomFields(): void
    {
        $transactionId = 'test-id';
        $customFields = ['key' => 'value'];
        $context = Context::createDefaultContext();

        $this->writer->expects($this->once())
            ->method('write')
            ->with([
                'id' => $transactionId,
                'customFields' => $customFields,
            ], $context);

        $this->service->updateCustomFields($transactionId, $customFields, $context);
    }
}
