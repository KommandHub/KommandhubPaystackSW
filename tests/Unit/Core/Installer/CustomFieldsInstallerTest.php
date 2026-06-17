<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Unit\Core\Installer;

use Kommandhub\PaystackSW\Core\Installer\CustomFieldsInstaller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

#[CoversClass(CustomFieldsInstaller::class)]
class CustomFieldsInstallerTest extends TestCase
{
    private EntityRepository $customFieldSetRepository;
    private EntityRepository $customFieldSetRelationRepository;
    private CustomFieldsInstaller $installer;
    private Context $context;

    protected function setUp(): void
    {
        $this->customFieldSetRepository = $this->createMock(EntityRepository::class);
        $this->customFieldSetRelationRepository = $this->createMock(EntityRepository::class);
        $this->installer = new CustomFieldsInstaller(
            $this->customFieldSetRepository,
            $this->customFieldSetRelationRepository
        );
        $this->context = Context::createDefaultContext();
    }

    public function testInstallWhenAlreadyExists(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn(['existing-id']);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $this->customFieldSetRepository->expects($this->never())->method('upsert');

        $this->installer->install($this->context);
    }

    public function testInstallWhenNotExists(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn([]);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $this->customFieldSetRepository->expects($this->once())->method('upsert');

        $this->installer->install($this->context);
    }

    public function testAddRelations(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn(['fieldset-id']);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $relationSearchResult = $this->createMock(IdSearchResult::class);
        $relationSearchResult->method('getTotal')->willReturn(0);
        $this->customFieldSetRelationRepository->method('searchIds')->willReturn($relationSearchResult);

        $this->customFieldSetRelationRepository->expects($this->once())->method('upsert')->with([
            [
                'customFieldSetId' => 'fieldset-id',
                'entityName' => 'customer',
            ],
        ], $this->context);

        $this->installer->addRelations($this->context);
    }

    public function testAddRelationsWhenAlreadyExists(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn(['fieldset-id']);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $relationSearchResult = $this->createMock(IdSearchResult::class);
        $relationSearchResult->method('getTotal')->willReturn(1);
        $this->customFieldSetRelationRepository->method('searchIds')->willReturn($relationSearchResult);

        $this->customFieldSetRelationRepository->expects($this->never())->method('upsert');

        $this->installer->addRelations($this->context);
    }

    public function testUninstall(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn(['fieldset-id']);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $this->customFieldSetRepository->expects($this->once())->method('delete')->with([
            ['id' => 'fieldset-id'],
        ], $this->context);

        $this->installer->uninstall($this->context);
    }

    public function testUninstallWhenNothingToDelete(): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('getIds')->willReturn([]);
        $this->customFieldSetRepository->method('searchIds')->willReturn($idSearchResult);

        $this->customFieldSetRepository->expects($this->never())->method('delete');

        $this->installer->uninstall($this->context);
    }
}
