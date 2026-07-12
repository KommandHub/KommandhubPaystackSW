<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Payment\Infrastructure\Shopware\EntityHandler;

use Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\EntityHandler\OrderTransactionWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

#[CoversClass(OrderTransactionWriter::class)]
class OrderTransactionWriterTest extends TestCase
{
    private EntityRepository&MockObject $repository;
    private OrderTransactionWriter $writer;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->writer = new OrderTransactionWriter($this->repository);
    }

    public function testGetRepository(): void
    {
        $reflection = new \ReflectionClass(OrderTransactionWriter::class);
        $method = $reflection->getMethod('getRepository');
        $method->setAccessible(true);

        $returnedRepository = $method->invoke($this->writer);
        $this->assertSame($this->repository, $returnedRepository);
    }
}
