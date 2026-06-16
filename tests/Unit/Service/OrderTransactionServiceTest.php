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

    public function testGetSuccess(): void
    {
        $context = Context::createDefaultContext();
        $transaction = $this->createMock(OrderTransactionEntity::class);
        $this->reader->method('readOneById')->willReturn($transaction);

        $result = $this->service->get('id', $context);
        $this->assertSame($transaction, $result);
    }

    public function testGetThrowsExceptionOnNotFound(): void
    {
        $context = Context::createDefaultContext();
        $this->reader->method('readOneById')->willReturn(null);

        $this->expectException(PaymentException::class);
        $this->service->get('id', $context);
    }

    public function testUpdateCustomFields(): void
    {
        $context = Context::createDefaultContext();
        $this->writer->expects($this->once())->method('write')->with([
            'id' => 'id',
            'customFields' => ['foo' => 'bar'],
        ], $context);

        $this->service->updateCustomFields('id', ['foo' => 'bar'], $context);
    }
}
