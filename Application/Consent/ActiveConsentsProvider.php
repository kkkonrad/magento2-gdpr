<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Consent;

use Kkkonrad\Gdpr\Api\Consent\ActiveConsentsProviderInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\Consent\ConsentFeatureResolver;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Magento\Framework\App\ResourceConnection;

class ActiveConsentsProvider implements ActiveConsentsProviderInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly FeatureManagerInterface $featureManager,
        private readonly ConsentFeatureResolver $featureResolver
    ) {
    }

    public function getForLocation(string $location, int $storeId): array
    {
        ConsentLocation::assertValid($location);
        if (!$this->featureManager->isEnabled($this->featureResolver->forLocation($location), $storeId)) {
            return [];
        }

        $definitionTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition');
        $storeTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition_store');
        $versionTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_version');
        $connection = $this->resourceConnection->getConnection();
        $definitions = $connection->fetchAll(
            $connection->select()
                ->from($definitionTable, ['definition_id', 'code', 'location', 'purpose', 'is_required', 'sort_order'])
                ->where('location = ?', $location)
                ->where('is_active = ?', 1)
                ->order('sort_order ASC')
        );

        $result = [];
        foreach ($definitions as $definition) {
            $definitionId = (int)$definition['definition_id'];
            $scopeStoreId = $this->resolveStoreScope($definitionId, $storeId, $storeTable);
            if ($scopeStoreId === null) {
                continue;
            }
            $version = $connection->fetchRow(
                $connection->select()
                    ->from($versionTable, ['version_id', 'version', 'content_snapshot', 'content_hash'])
                    ->where('definition_id = ?', $definitionId)
                    ->where('store_id = ?', $scopeStoreId)
                    ->where('valid_to IS NULL')
                    ->order('version DESC')
                    ->limit(1)
            );
            if ($version === false) {
                continue;
            }
            $snapshot = json_decode((string)$version['content_snapshot'], true, 16, JSON_THROW_ON_ERROR);
            $result[] = [
                'definition_id' => $definitionId,
                'version_id' => (int)$version['version_id'],
                'version' => (int)$version['version'],
                'code' => (string)$definition['code'],
                'location' => (string)$definition['location'],
                'purpose' => (string)($snapshot['purpose'] ?? $definition['purpose']),
                'is_required' => (bool)($snapshot['is_required'] ?? $definition['is_required']),
                'content' => (string)($snapshot['content'] ?? ''),
                'content_hash' => (string)$version['content_hash'],
            ];
        }

        return $result;
    }

    private function resolveStoreScope(int $definitionId, int $storeId, string $storeTable): ?int
    {
        $connection = $this->resourceConnection->getConnection();
        foreach (array_unique([$storeId, 0]) as $candidateStoreId) {
            $rows = $connection->fetchAll(
                $connection->select()
                    ->from($storeTable, ['is_active'])
                    ->where('definition_id = ?', $definitionId)
                    ->where('store_id = ?', $candidateStoreId)
            );
            if ($rows !== []) {
                return (bool)$rows[0]['is_active'] ? $candidateStoreId : null;
            }
        }

        return null;
    }
}
