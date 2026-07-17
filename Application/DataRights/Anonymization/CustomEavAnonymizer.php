<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Anonymization;

use Kkkonrad\Gdpr\Api\ConfigProviderInterface;
use Kkkonrad\Gdpr\Api\DataRights\PersonalDataAnonymizerInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\App\ResourceConnection;

class CustomEavAnonymizer implements PersonalDataAnonymizerInterface
{
    private const BACKEND_TYPES = ['varchar', 'int', 'text', 'datetime', 'decimal'];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly EavConfig $eavConfig,
        private readonly ConfigProviderInterface $configProvider
    ) {
    }

    public function getCode(): string
    {
        return 'custom_eav_attributes';
    }

    public function getPriority(): int
    {
        return 15;
    }

    /** @return array<string, int> */
    public function anonymize(int $customerId, string $operationKey): array
    {
        unset($operationKey);
        return [
            'customer_custom_attributes' => $this->clearValues(
                'customer',
                'customer_entity',
                [$customerId],
                $this->configProvider->getCsv(
                    'kkkonrad_gdpr/data_rights/anonymization_customer_attribute_codes'
                )
            ),
            'address_custom_attributes' => $this->clearAddressValues($customerId),
        ];
    }

    private function clearAddressValues(int $customerId): int
    {
        $connection = $this->resourceConnection->getConnection();
        $entityTable = $this->resourceConnection->getTableName('customer_address_entity');
        $addressIds = array_map('intval', $connection->fetchCol(
            $connection->select()->from($entityTable, ['entity_id'])->where('parent_id = ?', $customerId)
        ));
        return $this->clearValues(
            'customer_address',
            'customer_address_entity',
            $addressIds,
            $this->configProvider->getCsv(
                'kkkonrad_gdpr/data_rights/anonymization_address_attribute_codes'
            )
        );
    }

    /**
     * @param int[] $entityIds
     * @param string[] $attributeCodes
     */
    private function clearValues(
        string $entityType,
        string $entityTable,
        array $entityIds,
        array $attributeCodes
    ): int {
        if ($entityIds === [] || $attributeCodes === []) {
            return 0;
        }
        $connection = $this->resourceConnection->getConnection();
        $cleared = 0;
        foreach ($attributeCodes as $code) {
            $attribute = $this->eavConfig->getAttribute($entityType, $code);
            $attributeId = (int)$attribute->getId();
            $backendType = (string)$attribute->getBackendType();
            if ($attributeId <= 0 || !$attribute->getIsUserDefined() || !in_array($backendType, self::BACKEND_TYPES, true)) {
                continue;
            }
            $cleared += $connection->delete(
                $this->resourceConnection->getTableName($entityTable . '_' . $backendType),
                ['attribute_id = ?' => $attributeId, 'entity_id IN (?)' => $entityIds]
            );
        }
        return $cleared;
    }
}
