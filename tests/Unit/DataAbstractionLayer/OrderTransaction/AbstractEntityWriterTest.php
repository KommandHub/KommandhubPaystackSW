<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\DataAbstractionLayer\OrderTransaction;

use Kommandhub\PaystackSW\DataAbstractionLayer\OrderTransaction\AbstractEntityWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

#[CoversClass(AbstractEntityWriter::class)]
class AbstractEntityWriterTest extends TestCase
{
    private EntityRepository&MockObject $repository;
    private AbstractEntityWriter $writer;
    private Context $context;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->context = Context::createDefaultContext();

        $this->writer = new class($this->repository) extends AbstractEntityWriter {
        };
    }

    public function testWrite(): void
    {
        $id = 'test-id';
        $data = ['id' => $id, 'name' => 'test'];

        $this->repository->expects($this->once())
            ->method('upsert')
            ->with([$data], $this->context);

        $returnedId = $this->writer->write($data, $this->context);
        $this->assertSame($id, $returnedId);
    }

    public function testWriteGeneratesId(): void
    {
        $data = ['name' => 'test'];

        $this->repository->expects($this->once())
            ->method('upsert')
            ->with($this->callback(function (array $payload) {
                return isset($payload[0]['id']) && is_string($payload[0]['id']);
            }), $this->context);

        $returnedId = $this->writer->write($data, $this->context);
        $this->assertIsString($returnedId);
    }

    public function testWriteThrowsExceptionOnInvalidId(): void
    {
        $data = ['id' => 123];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity ID must be a string');

        $this->writer->write($data, $this->context);
    }

    public function testWriteMany(): void
    {
        $rows = [
            ['id' => 'id1', 'name' => 'name1'],
            ['name' => 'name2'],
        ];

        $this->repository->expects($this->once())
            ->method('upsert')
            ->with($this->callback(function (array $payload) {
                return count($payload) === 2 &&
                       $payload[0]['id'] === 'id1' &&
                       isset($payload[1]['id']);
            }), $this->context);

        $ids = $this->writer->writeMany($rows, $this->context);
        $this->assertCount(2, $ids);
        $this->assertSame('id1', $ids[0]);
        $this->assertIsString($ids[1]);
    }

    public function testWriteManyEmpty(): void
    {
        $this->repository->expects($this->never())->method('upsert');

        $ids = $this->writer->writeMany([], $this->context);
        $this->assertSame([], $ids);
    }

    public function testWriteManyThrowsExceptionOnInvalidId(): void
    {
        $rows = [
            ['id' => 123],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Entity ID must be a string');

        $this->writer->writeMany($rows, $this->context);
    }
}
