<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Cookie;

use Kkkonrad\Gdpr\Api\Cookie\CookieRegistryInterface;
use Magento\Framework\App\ResourceConnection;

class CookieRegistry implements CookieRegistryInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getGroups(int $storeId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $groupTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_group');
        $groupStoreTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_group_store');
        $cookieTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie');
        $cookieStoreTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_store');
        $groups = $connection->fetchAll(
            $connection->select()
                ->from($groupTable, ['group_id', 'code', 'type', 'is_required', 'priority'])
                ->where('is_active = ?', 1)
                ->order('priority ASC')
        );

        $result = [];
        foreach ($groups as $group) {
            $groupId = (int)$group['group_id'];
            $translation = $this->getScopedRow($groupStoreTable, 'group_id', $groupId, $storeId);
            $cookies = $connection->fetchAll(
                $connection->select()
                    ->from($cookieTable, ['cookie_id', 'name', 'code_pattern', 'storage_type', 'lifetime'])
                    ->where('group_id = ?', $groupId)
                    ->where('is_active = ?', 1)
                    ->order('name ASC')
            );
            $localizedCookies = [];
            foreach ($cookies as $cookie) {
                $scope = $this->getScopedRow(
                    $cookieStoreTable,
                    'cookie_id',
                    (int)$cookie['cookie_id'],
                    $storeId
                );
                if ($scope !== null && !(bool)$scope['is_active']) {
                    continue;
                }
                $cookie['description'] = (string)($scope['description'] ?? '');
                $localizedCookies[] = $cookie;
            }
            $result[] = [
                'group_id' => $groupId,
                'code' => (string)$group['code'],
                'type' => (string)$group['type'],
                'is_required' => (bool)$group['is_required'],
                'priority' => (int)$group['priority'],
                'name' => (string)($translation['name'] ?? $group['code']),
                'description' => (string)($translation['description'] ?? ''),
                'cookies' => $localizedCookies,
            ];
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getScopedRow(
        string $table,
        string $idField,
        int $id,
        int $storeId
    ): ?array {
        $connection = $this->resourceConnection->getConnection();
        foreach (array_unique([$storeId, 0]) as $candidateStoreId) {
            $row = $connection->fetchRow(
                $connection->select()
                    ->from($table)
                    ->where($idField . ' = ?', $id)
                    ->where('store_id = ?', $candidateStoreId)
            );
            if ($row !== false) {
                return $row;
            }
        }

        return null;
    }
}
