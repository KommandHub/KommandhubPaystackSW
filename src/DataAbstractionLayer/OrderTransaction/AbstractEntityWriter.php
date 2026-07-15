<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\DataAbstractionLayer\OrderTransaction;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Base abstraction for Shopware DAL entity write operations.
 *
 * @template TCollection of \Shopware\Core\Framework\DataAbstractionLayer\EntityCollection
 */
abstract class AbstractEntityWriter
{
    /**
     * @param EntityRepository<TCollection> $repository The repository for the entity to be written
     */
    public function __construct(
        protected EntityRepository $repository
    ) {
    }

    /**
     * Creates or updates a single entity.
     *
     * @param array<string, mixed> $data Entity payload
     * @param Context $context Shopware execution context
     *
     * @return string Persisted entity ID
     */
    public function write(
        array $data,
        Context $context
    ): string {
        $id = $data['id'] ?? Uuid::randomHex();

        if (!is_string($id)) {
            throw new \InvalidArgumentException('Entity ID must be a string');
        }
        $data['id'] = $id;

        $this->repository->upsert([$data], $context);

        return $id;
    }

    /**
     * Creates or updates multiple entities.
     *
     * @param array<int, array<string, mixed>> $rows Entity payloads
     * @param Context $context Shopware execution context
     *
     * @return array<string> Persisted entity IDs
     */
    public function writeMany(
        array $rows,
        Context $context
    ): array {
        if ($rows === []) {
            return [];
        }

        $ids = [];

        foreach ($rows as &$row) {
            $id = $row['id'] ?? Uuid::randomHex();

            if (!is_string($id)) {
                throw new \InvalidArgumentException('Entity ID must be a string');
            }
            $row['id'] = $id;
            $ids[] = $id;
        }
        unset($row);

        $this->repository->upsert($rows, $context);

        return $ids;
    }
}
