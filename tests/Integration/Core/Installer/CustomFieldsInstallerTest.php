<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Tests\Integration\Core\Installer;

use Kommandhub\PaystackSW\Core\Installer\CustomFieldsInstaller;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

#[CoversClass(CustomFieldsInstaller::class)]
class CustomFieldsInstallerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private CustomFieldsInstaller $installer;
    private EntityRepository $customFieldSetRepository;
    private EntityRepository $customFieldSetRelationRepository;

    protected function setUp(): void
    {
        $this->customFieldSetRepository = static::getContainer()->get('custom_field_set.repository');
        $this->customFieldSetRelationRepository = static::getContainer()->get('custom_field_set_relation.repository');

        $this->installer = new CustomFieldsInstaller(
            $this->customFieldSetRepository,
            $this->customFieldSetRelationRepository
        );
    }

    public function testInstallAndAddRelationsPersistData(): void
    {
        $context = Context::createDefaultContext();

        $this->installer->install($context);
        $this->installer->addRelations($context);

        $setId = $this->getCustomFieldSetId($context);
        self::assertNotNull($setId);

        $relationCriteria = (new Criteria())
            ->addFilter(new EqualsFilter('customFieldSetId', $setId))
            ->addFilter(new EqualsFilter('entityName', CustomerDefinition::ENTITY_NAME));

        self::assertGreaterThan(
            0,
            $this->customFieldSetRelationRepository->searchIds($relationCriteria, $context)->getTotal()
        );
    }

    public function testUninstallRemovesData(): void
    {
        $context = Context::createDefaultContext();

        $this->installer->install($context);
        self::assertNotNull($this->getCustomFieldSetId($context));

        $this->installer->uninstall($context);

        self::assertNull($this->getCustomFieldSetId($context));
    }

    private function getCustomFieldSetId(Context $context): ?string
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('name', 'kommandhub_paystack_fieldset'));

        return $this->customFieldSetRepository->searchIds($criteria, $context)->firstId();
    }
}
