<?php

declare(strict_types=1);

namespace Kommandhub\PaystackSW\Migration;

use DateTime;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1780389223AddAfricanLanguages extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1780389223;
    }

    public function update(Connection $connection): void
    {
        $languages = [
            ['name' => 'Afrikaans', 'locale' => 'af-ZA'],
            ['name' => 'Amharic', 'locale' => 'am-ET'],
            ['name' => 'Akan', 'locale' => 'ak-GH'],
            ['name' => 'Arabic (Algeria)', 'locale' => 'ar-DZ'],
            ['name' => 'Arabic (Egypt)', 'locale' => 'ar-EG'],
            ['name' => 'Arabic (Libya)', 'locale' => 'ar-LY'],
            ['name' => 'Arabic (Morocco)', 'locale' => 'ar-MA'],
            ['name' => 'Arabic (Tunisia)', 'locale' => 'ar-TN'],
            ['name' => 'Berber (Algeria)', 'locale' => 'ber-DZ'],
            ['name' => 'Chewa', 'locale' => 'ny-MW'],
            ['name' => 'Dyula', 'locale' => 'dyu-BF'],
            ['name' => 'Fon', 'locale' => 'fon-BJ'],
            ['name' => 'Hausa', 'locale' => 'ha-NG'],
            ['name' => 'Igbo', 'locale' => 'ig-NG'],
            ['name' => 'Kinyarwanda', 'locale' => 'rw-RW'],
            ['name' => 'Kirundi', 'locale' => 'rn-BI'],
            ['name' => 'Lingala', 'locale' => 'ln-CD'],
            ['name' => 'Luganda', 'locale' => 'lg-UG'],
            ['name' => 'Malagasy', 'locale' => 'mg-MG'],
            ['name' => 'Oromo', 'locale' => 'om-ET'],
            ['name' => 'Sesotho', 'locale' => 'st-LS'],
            ['name' => 'Shona', 'locale' => 'sn-ZW'],
            ['name' => 'Somali', 'locale' => 'so-SO'],
            ['name' => 'Swahili (Kenya)', 'locale' => 'sw-KE'],
            ['name' => 'Swahili (Tanzania)', 'locale' => 'sw-TZ'],
            ['name' => 'Tigrinya', 'locale' => 'ti-ET'],
            ['name' => 'Tswana', 'locale' => 'tn-BW'],
            ['name' => 'Wolof', 'locale' => 'wo-SN'],
            ['name' => 'Xhosa', 'locale' => 'xh-ZA'],
            ['name' => 'Yoruba', 'locale' => 'yo-NG'],
            ['name' => 'Zulu', 'locale' => 'zu-ZA'],
        ];

        foreach ($languages as $language) {
            $this->addLanguage($connection, $language['name'], $language['locale']);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addLanguage(Connection $connection, string $name, string $localeCode): void
    {
        $languageId = $connection->fetchOne('SELECT id FROM language WHERE name = :name', ['name' => $name]);

        if (!$languageId) {
            $localeId = $this->getLocaleId($connection, $localeCode);

            $languageId = Uuid::randomBytes();
            $connection->insert('language', [
                'id' => $languageId,
                'name' => $name,
                'locale_id' => $localeId,
                'translation_code_id' => $localeId,
                'active' => 1,
                'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function getLocaleId(Connection $connection, string $localeCode): string
    {
        $localeId = $connection->fetchOne('SELECT id FROM locale WHERE code = :code', ['code' => $localeCode]);

        if (!$localeId) {
            $localeId = Uuid::randomBytes();
            $connection->insert('locale', [
                'id' => $localeId,
                'code' => $localeCode,
                'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            // Locales need translations in Shopware
            $enLanguageId = $this->getEnLanguageId($connection);
            if ($enLanguageId) {
                $this->upsertLocaleTranslation($connection, (string) $localeId, $enLanguageId, $localeCode);
            }
        }

        if (!is_string($localeId)) {
            throw new \RuntimeException('Failed to determine locale ID');
        }

        return $localeId;
    }

    private function upsertLocaleTranslation(Connection $connection, string $localeId, string $languageId, string $name): void
    {
        $exists = $connection->fetchOne(
            'SELECT 1 FROM locale_translation WHERE locale_id = :localeId AND language_id = :languageId',
            ['localeId' => $localeId, 'languageId' => $languageId]
        );

        if (!$exists) {
            $connection->insert('locale_translation', [
                'locale_id' => $localeId,
                'language_id' => $languageId,
                'name' => $name,
                'territory' => '',
                'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function getEnLanguageId(Connection $connection): string
    {
        $id = $connection->fetchOne(
            'SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`translation_code_id` = `locale`.`id` WHERE `locale`.`code` = :code',
            ['code' => 'en-GB']
        );

        if (!$id) {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        if (!is_string($id)) {
            throw new \RuntimeException('Failed to determine English language ID');
        }

        return $id;
    }
}
