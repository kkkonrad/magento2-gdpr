<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Cookie;

use Kkkonrad\Gdpr\Api\Cookie\CookiePolicyVersionProviderInterface;
use Kkkonrad\Gdpr\Api\Cookie\CookieRegistryInterface;
use Kkkonrad\Gdpr\Api\RandomIdGeneratorInterface;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class CookiePolicyVersionProvider implements CookiePolicyVersionProviderInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CookieRegistryInterface $cookieRegistry,
        private readonly RandomIdGeneratorInterface $randomIdGenerator
    ) {
    }

    public function getOrPublishCurrent(int $storeId): array
    {
        $configuration = json_encode(
            $this->cookieRegistry->getGroups($storeId),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        $configurationHash = hash('sha256', $configuration);
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_policy_version');
        $connection = $this->resourceConnection->getConnection();
        $current = $connection->fetchRow(
            $connection->select()
                ->from($table, ['policy_version_id', 'public_id', 'version', 'configuration_hash'])
                ->where('store_id = ?', $storeId)
                ->order('version DESC')
                ->limit(1)
        );
        if ($current !== false && hash_equals((string)$current['configuration_hash'], $configurationHash)) {
            return $this->normalize($current);
        }

        $connection->beginTransaction();
        try {
            $lastVersion = (int)$connection->fetchOne(
                $connection->select()
                    ->from($table, ['MAX(version)'])
                    ->where('store_id = ?', $storeId)
                    ->forUpdate(true)
            );
            $connection->insert($table, [
                'store_id' => $storeId,
                'public_id' => $this->randomIdGenerator->uuid(),
                'version' => $lastVersion + 1,
                'configuration_hash' => $configurationHash,
            ]);
            $id = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
            $row = $connection->fetchRow(
                $connection->select()
                    ->from($table, ['policy_version_id', 'public_id', 'version', 'configuration_hash'])
                    ->where('policy_version_id = ?', $id)
            );
            $connection->commit();

            return $this->normalize($row ?: []);
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $row
     * @return array{policy_version_id:int, public_id:string, version:int, configuration_hash:string}
     */
    private function normalize(array $row): array
    {
        return [
            'policy_version_id' => (int)($row['policy_version_id'] ?? 0),
            'public_id' => (string)($row['public_id'] ?? ''),
            'version' => (int)($row['version'] ?? 0),
            'configuration_hash' => (string)($row['configuration_hash'] ?? ''),
        ];
    }
}
