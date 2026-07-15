<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\DataAbstractionLayer\OrderTransactionCaptureRefund;

use Kommandhub\PaystackSW\DataAbstractionLayer\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundReader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

#[CoversClass(OrderTransactionCaptureRefundReader::class)]
class OrderTransactionCaptureRefundReaderTest extends TestCase
{
    private EntityRepository&MockObject $repository;

    private OrderTransactionCaptureRefundReader $reader;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->reader = new OrderTransactionCaptureRefundReader($this->repository);
        $this->context = Context::createDefaultContext();
    }

    public function testGetRepository(): void
    {
        $reflection = new \ReflectionClass(OrderTransactionCaptureRefundReader::class);
        $method = $reflection->getMethod('getRepository');
        $method->setAccessible(true);

        $returnedRepository = $method->invoke($this->reader);
        $this->assertSame($this->repository, $returnedRepository);
    }

    public function testReadIdOfOne(): void
    {
        $criteria = new Criteria();
        $id = 'test-id';

        $result = $this->createMock(IdSearchResult::class);
        $result->method('firstId')->willReturn($id);

        $this->repository->expects($this->once())
            ->method('searchIds')
            ->with($this->callback(function (Criteria $c) use ($criteria) {
                return $c !== $criteria; // Criteria should be cloned
            }), $this->context)
            ->willReturn($result);

        $returnedId = $this->reader->readIdOfOne($criteria, $this->context);
        $this->assertSame($id, $returnedId);
    }

    public function testReadIdOfOneReturnsNull(): void
    {
        $criteria = new Criteria();

        $result = $this->createMock(IdSearchResult::class);
        $result->method('firstId')->willReturn(null);

        $this->repository->expects($this->once())
            ->method('searchIds')
            ->willReturn($result);

        $returnedId = $this->reader->readIdOfOne($criteria, $this->context);
        $this->assertNull($returnedId);
    }
}
