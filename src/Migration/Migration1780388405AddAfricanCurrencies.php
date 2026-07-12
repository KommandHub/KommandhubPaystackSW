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
class Migration1780388405AddAfricanCurrencies extends MigrationStep
{
    private ?string $deLanguageId = null;

    private ?string $enLanguageId = null;

    public function getCreationTimestamp(): int
    {
        return 1780388405;
    }

    public function update(Connection $connection): void
    {
        $this->addCurrency($connection, 'DZD', 1.0, 'د.ج', 'DZD', 'DZD', 'Algerischer Dinar', 'Algerian Dinar');
        $this->addCurrency($connection, 'AOA', 1.0, 'Kz', 'AOA', 'AOA', 'Angolanischer Kwanza', 'Angolan Kwanza');
        $this->addCurrency($connection, 'BWP', 1.0, 'P', 'BWP', 'BWP', 'Botsuanischer Pula', 'Botswana Pula');
        $this->addCurrency($connection, 'BIF', 1.0, 'FBu', 'BIF', 'BIF', 'Burundi-Franc', 'Burundian Franc');
        $this->addCurrency($connection, 'CVE', 1.0, '$', 'CVE', 'CVE', 'Kap-Verde-Escudo', 'Cape Verdean Escudo');
        $this->addCurrency($connection, 'XAF', 1.0, 'FCFA', 'XAF', 'XAF', 'CFA-Franc (BEAC)', 'Central African CFA Franc');
        $this->addCurrency($connection, 'KMF', 1.0, 'CF', 'KMF', 'KMF', 'Komoren-Franc', 'Comorian Franc');
        $this->addCurrency($connection, 'CDF', 1.0, 'FC', 'CDF', 'CDF', 'Kongo-Franc', 'Congolese Franc');
        $this->addCurrency($connection, 'DJF', 1.0, 'Fdj', 'DJF', 'DJF', 'Dschibuti-Franc', 'Djiboutian Franc');
        $this->addCurrency($connection, 'EGP', 1.0, 'E£', 'EGP', 'EGP', 'Ägyptisches Pfund', 'Egyptian Pound');
        $this->addCurrency($connection, 'ERN', 1.0, 'Nkf', 'ERN', 'ERN', 'Eritreischer Nakfa', 'Eritrean Nakfa');
        $this->addCurrency($connection, 'ETB', 1.0, 'Br', 'ETB', 'ETB', 'Äthiopischer Birr', 'Ethiopian Birr');
        $this->addCurrency($connection, 'GMD', 1.0, 'D', 'GMD', 'GMD', 'Gambia-Dalasi', 'Gambian Dalasi');
        $this->addCurrency($connection, 'GHS', 1.0, 'GH₵', 'GHS', 'GHS', 'Ghanaischer Cedi', 'Ghanaian Cedi');
        $this->addCurrency($connection, 'GNF', 1.0, 'FG', 'GNF', 'GNF', 'Guinea-Franc', 'Guinean Franc');
        $this->addCurrency($connection, 'KES', 1.0, 'KSh', 'KES', 'KES', 'Kenia-Schilling', 'Kenyan Shilling');
        $this->addCurrency($connection, 'LSL', 1.0, 'L', 'LSL', 'LSL', 'Lesothischer Loti', 'Lesotho Loti');
        $this->addCurrency($connection, 'LRD', 1.0, 'L$', 'LRD', 'LRD', 'Liberianischer Dollar', 'Liberian Dollar');
        $this->addCurrency($connection, 'LYD', 1.0, 'LD', 'LYD', 'LYD', 'Libyscher Dinar', 'Libyan Dinar');
        $this->addCurrency($connection, 'MGA', 1.0, 'Ar', 'MGA', 'MGA', 'Madagaskar-Ariary', 'Malagasy Ariary');
        $this->addCurrency($connection, 'MWK', 1.0, 'K', 'MWK', 'MWK', 'Malawi-Kwacha', 'Malawian Kwacha');
        $this->addCurrency($connection, 'MRU', 1.0, 'UM', 'MRU', 'MRU', 'Mauretanischer Ouguiya', 'Mauritanian Ouguiya');
        $this->addCurrency($connection, 'MUR', 1.0, '₨', 'MUR', 'MUR', 'Mauritius-Rupie', 'Mauritian Rupee');
        $this->addCurrency($connection, 'MAD', 1.0, 'DH', 'MAD', 'MAD', 'Marokkanischer Dirham', 'Moroccan Dirham');
        $this->addCurrency($connection, 'MZN', 1.0, 'MT', 'MZN', 'MZN', 'Mosambikanischer Metical', 'Mozambican Metical');
        $this->addCurrency($connection, 'NAD', 1.0, 'N$', 'NAD', 'NAD', 'Namibia-Dollar', 'Namibian Dollar');
        $this->addCurrency($connection, 'NGN', 1.0, '₦', 'NGN', 'NGN', 'Nigerianischer Naira', 'Nigerian Naira');
        $this->addCurrency($connection, 'RWF', 1.0, 'FRw', 'RWF', 'RWF', 'Ruanda-Franc', 'Rwandan Franc');
        $this->addCurrency($connection, 'STN', 1.0, 'Db', 'STN', 'STN', 'São-toméischer Dobra', 'São Tomé and Príncipe Dobra');
        $this->addCurrency($connection, 'SCR', 1.0, '₨', 'SCR', 'SCR', 'Seychellen-Rupie', 'Seychellois Rupee');
        $this->addCurrency($connection, 'SLL', 1.0, 'Le', 'SLL', 'SLL', 'Sierra-leonischer Leone', 'Sierra Leonean Leone');
        $this->addCurrency($connection, 'SOS', 1.0, 'S', 'SOS', 'SOS', 'Somalia-Schilling', 'Somali Shilling');
        $this->addCurrency($connection, 'ZAR', 1.0, 'R', 'ZAR', 'ZAR', 'Südafrikanischer Rand', 'South African Rand');
        $this->addCurrency($connection, 'SSP', 1.0, '£', 'SSP', 'SSP', 'Südsudanesisches Pfund', 'South Sudanese Pound');
        $this->addCurrency($connection, 'SDG', 1.0, '£', 'SDG', 'SDG', 'Sudanesisches Pfund', 'Sudanese Pound');
        $this->addCurrency($connection, 'SZL', 1.0, 'L', 'SZL', 'SZL', 'Swasiländischer Lilangeni', 'Swazi Lilangeni');
        $this->addCurrency($connection, 'TZS', 1.0, 'TSh', 'TZS', 'TZS', 'Tansania-Schilling', 'Tanzanian Shilling');
        $this->addCurrency($connection, 'TND', 1.0, 'DT', 'TND', 'TND', 'Tunesischer Dinar', 'Tunisian Dinar');
        $this->addCurrency($connection, 'UGX', 1.0, 'USh', 'UGX', 'UGX', 'Uganda-Schilling', 'Ugandan Shilling');
        $this->addCurrency($connection, 'XOF', 1.0, 'CFA', 'XOF', 'XOF', 'CFA-Franc (BCEAO)', 'West African CFA Franc');
        $this->addCurrency($connection, 'ZMW', 1.0, 'ZK', 'ZMW', 'ZMW', 'Sambischer Kwacha', 'Zambian Kwacha');
        $this->addCurrency($connection, 'ZWL', 1.0, 'Z$', 'ZWL', 'ZWL', 'Simbabwe-Dollar', 'Zimbabwean Dollar');
    }

    public function updateDestructive(Connection $connection): void
    {
    }

    private function addCurrency(
        Connection $connection,
        string $isoCode,
        float $factor,
        string $symbol,
        string $shortNameDe,
        string $shortNameEn,
        string $nameDe,
        string $nameEn
    ): void {
        $currencyId = $connection->fetchOne('SELECT id FROM currency WHERE iso_code = :isoCode', ['isoCode' => $isoCode]);

        if ($currencyId === false) {
            $currencyId = Uuid::randomBytes();
            $rounding = json_encode([
                'decimals' => 2,
                'roundForNet' => true,
                'interval' => 0.01,
            ], \JSON_THROW_ON_ERROR);

            $connection->insert('currency', [
                'id' => $currencyId,
                'iso_code' => $isoCode,
                'factor' => $factor,
                'symbol' => $symbol,
                'position' => 1,
                'item_rounding' => $rounding,
                'total_rounding' => $rounding,
                'tax_free_from' => 0,
                'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }

        if (!is_string($currencyId)) {
            throw new \RuntimeException('Failed to determine currency ID');
        }

        $enLanguageId = $this->getEnLanguageId($connection);
        $deLanguageId = $this->getDeLanguageId($connection);

        if ($enLanguageId) {
            $this->upsertTranslation($connection, $currencyId, $enLanguageId, $shortNameEn, $nameEn);
        }

        if ($deLanguageId) {
            $this->upsertTranslation($connection, $currencyId, $deLanguageId, $shortNameDe, $nameDe);
        }
    }

    private function upsertTranslation(
        Connection $connection,
        string $currencyId,
        string $languageId,
        string $shortName,
        string $name
    ): void {
        $exists = $connection->fetchOne(
            'SELECT 1 FROM currency_translation WHERE currency_id = :currencyId AND language_id = :languageId',
            ['currencyId' => $currencyId, 'languageId' => $languageId]
        );

        if ($exists) {
            $connection->update('currency_translation', [
                'short_name' => $shortName,
                'name' => $name,
                'updated_at' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], ['currency_id' => $currencyId, 'language_id' => $languageId]);
        } else {
            $connection->insert('currency_translation', [
                'currency_id' => $currencyId,
                'language_id' => $languageId,
                'short_name' => $shortName,
                'name' => $name,
                'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    private function getDeLanguageId(Connection $connection): ?string
    {
        if ($this->deLanguageId === null) {
            $this->deLanguageId = $this->fetchLanguageId($connection, 'de-DE');
        }

        return $this->deLanguageId;
    }

    private function getEnLanguageId(Connection $connection): ?string
    {
        if ($this->enLanguageId === null) {
            $this->enLanguageId = $this->fetchLanguageId($connection, 'en-GB');
        }

        return $this->enLanguageId;
    }

    private function fetchLanguageId(Connection $connection, string $localeCode): ?string
    {
        $id = $connection->fetchOne(
            'SELECT `language`.`id` FROM `language` INNER JOIN `locale` ON `language`.`translation_code_id` = `locale`.`id` WHERE `locale`.`code` = :code',
            ['code' => $localeCode]
        );

        if (!$id && $localeCode === 'en-GB') {
            return Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        }

        return is_string($id) ? $id : null;
    }
}
