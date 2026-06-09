<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Service;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\CustomField\CustomFieldTypes;

class CustomFieldsInstaller
{
    private const CUSTOM_FIELDSET_NAME = 'kommandhub_paystack_fieldset';

    private const CUSTOM_FIELDSET = [
        'name' => self::CUSTOM_FIELDSET_NAME,
        'config' => [
            'label' => [
                'en-GB' => 'Paystack Bank Fields',
                'de-DE' => 'Paystack Bankfelder',
                'fr-FR' => 'Champs bancaires Paystack',
                Defaults::LANGUAGE_SYSTEM => 'Paystack Fields',
            ],
        ],
        'customFields' => [
            [
                'name' => 'kommandhub_paystack_bank_name',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Bank Name',
                        'de-DE' => 'Name der Bank',
                        'fr-FR' => 'Nom de la banque',
                        Defaults::LANGUAGE_SYSTEM => 'Bank Name',
                    ],
                    'customFieldPosition' => 1,
                ],
            ],
            [
                'name' => 'kommandhub_paystack_bank_code',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Bank Code',
                        'de-DE' => 'Bankleitzahl',
                        'fr-FR' => 'Code de la banque',
                        Defaults::LANGUAGE_SYSTEM => 'Bank Code',
                    ],
                    'customFieldPosition' => 2,
                ],
            ],
            [
                'name' => 'kommandhub_paystack_account_number',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Account Number',
                        'de-DE' => 'Kontonummer',
                        'fr-FR' => 'Numéro de compte',
                        Defaults::LANGUAGE_SYSTEM => 'Account Number',
                    ],
                    'customFieldPosition' => 3,
                ],
            ],
            [
                'name' => 'kommandhub_paystack_account_name',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'Account Name',
                        'de-DE' => 'Kontoinhaber',
                        'fr-FR' => 'Nom du titulaire',
                        Defaults::LANGUAGE_SYSTEM => 'Account Name',
                    ],
                    'customFieldPosition' => 4,
                ],
            ],
            [
                'name' => 'kommandhub_paystack_bvn',
                'type' => CustomFieldTypes::TEXT,
                'config' => [
                    'label' => [
                        'en-GB' => 'BVN',
                        'de-DE' => 'BVN',
                        'fr-FR' => 'BVN',
                        Defaults::LANGUAGE_SYSTEM => 'BVN',
                    ],
                    'helpText' => [
                        'en-GB' => 'Bank Verification Number',
                        'de-DE' => 'Bank Verification Number',
                        'fr-FR' => 'Numéro de vérification bancaire',
                        Defaults::LANGUAGE_SYSTEM => 'Bank Verification Number',
                    ],
                    'customFieldPosition' => 5,
                ],
            ],
        ],
    ];

    public function __construct(
        private readonly EntityRepository $customFieldSetRepository,
        private readonly EntityRepository $customFieldSetRelationRepository
    ) {
    }

    public function install(Context $context): void
    {
        if ($this->customFieldSetExists($context)) {
            return;
        }

        $this->customFieldSetRepository->upsert([
            self::CUSTOM_FIELDSET,
        ], $context);
    }

    public function addRelations(Context $context): void
    {
        $relationsToInsert = [];

        foreach ($this->getCustomFieldSetIds($context) as $customFieldSetId) {
            if ($this->customFieldSetRelationExists($context, $customFieldSetId, CustomerDefinition::ENTITY_NAME)) {
                continue;
            }

            $relationsToInsert[] = [
                'customFieldSetId' => $customFieldSetId,
                'entityName' => CustomerDefinition::ENTITY_NAME,
            ];
        }

        if ($relationsToInsert === []) {
            return;
        }

        $this->customFieldSetRelationRepository->upsert($relationsToInsert, $context);
    }

    public function uninstall(Context $context): void
    {
        $ids = $this->getCustomFieldSetIds($context);

        if ($ids === []) {
            return;
        }

        $ids = array_map(static fn (string $id) => ['id' => $id], $ids);

        $this->customFieldSetRepository->delete($ids, $context);
    }

    /**
     * @return string[]
     */
    private function getCustomFieldSetIds(Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', self::CUSTOM_FIELDSET_NAME));

        return $this->customFieldSetRepository->searchIds($criteria, $context)->getIds();
    }

    private function customFieldSetExists(Context $context): bool
    {
        return $this->getCustomFieldSetIds($context) !== [];
    }

    private function customFieldSetRelationExists(Context $context, string $customFieldSetId, string $entityName): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('customFieldSetId', $customFieldSetId));
        $criteria->addFilter(new EqualsFilter('entityName', $entityName));

        return $this->customFieldSetRelationRepository->searchIds($criteria, $context)->getTotal() > 0;
    }
}
