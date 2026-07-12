<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Payment\Infrastructure\Shopware\EntityHandler;

use Kommandhub\PaystackSW\Payment\Infrastructure\Shopware\EntityHandler\OrderTransactionReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

#[CoversClass(OrderTransactionReader::class)]
class OrderTransactionReaderTest extends TestCase
{
    private EntityRepository&MockObject $repository;
    private OrderTransactionReader $reader;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->reader = new OrderTransactionReader($this->repository);
    }

    public function testGetRepository(): void
    {
        $reflection = new \ReflectionClass(OrderTransactionReader::class);
        $method = $reflection->getMethod('getRepository');
        $method->setAccessible(true);

        $returnedRepository = $method->invoke($this->reader);
        $this->assertSame($this->repository, $returnedRepository);
    }
}
