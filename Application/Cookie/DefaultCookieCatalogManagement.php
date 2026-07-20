<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Cookie;

use Kkkonrad\Gdpr\Api\Cookie\CookiePolicyVersionProviderInterface;
use Kkkonrad\Gdpr\Domain\Cookie\DefaultCookieCatalog;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class DefaultCookieCatalogManagement
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DefaultCookieCatalog $defaultCatalog,
        private readonly CookiePolicyVersionProviderInterface $policyVersionProvider,
        private readonly TypeListInterface $cacheTypeList
    ) {
    }

    /**
     * @return array<int, array{kind:string,key:string,status:string,differences:string[]}>
     */
    public function diff(int $storeId = 0): array
    {
        $connection = $this->resourceConnection->getConnection();
        $groupTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_group');
        $groupStoreTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_group_store');
        $cookieTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie');
        $cookieStoreTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_store');
        $result = [];

        foreach ($this->defaultCatalog->groups() as $expected) {
            $actual = $connection->fetchRow(
                $connection->select()
                    ->from(['group' => $groupTable], ['code', 'type', 'required' => 'is_required', 'priority'])
                    ->joinLeft(
                        ['store' => $groupStoreTable],
                        $connection->quoteInto('store.group_id = group.group_id AND store.store_id = ?', $storeId),
                        ['name', 'description']
                    )
                    ->where('group.code = ?', $expected['code'])
                    ->limit(1)
            );
            $result[] = $this->compare('group', $expected['code'], $expected, $actual ?: null);
        }

        foreach ($this->defaultCatalog->storage() as $expected) {
            $actual = $connection->fetchRow(
                $connection->select()
                    ->from(['cookie' => $cookieTable], ['name', 'pattern' => 'code_pattern', 'type' => 'storage_type', 'lifetime'])
                    ->joinInner(['group' => $groupTable], 'group.group_id = cookie.group_id', ['group_code' => 'code'])
                    ->joinLeft(
                        ['store' => $cookieStoreTable],
                        $connection->quoteInto('store.cookie_id = cookie.cookie_id AND store.store_id = ?', $storeId),
                        ['description']
                    )
                    ->where('cookie.code_pattern = ?', $expected['pattern'])
                    ->where('cookie.storage_type = ?', $expected['type'])
                    ->limit(1)
            );
            $cookieExpected = $expected + ['group_code' => 'essential'];
            $result[] = $this->compare(
                'storage',
                $expected['type'] . ':' . $expected['pattern'],
                $cookieExpected,
                $actual ?: null
            );
        }

        return $result;
    }

    /** @return array{inserted:int,updated:int,policy_version:int} */
    public function restore(int $storeId = 0): array
    {
        $connection = $this->resourceConnection->getConnection();
        $groupTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_group');
        $groupStoreTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_group_store');
        $cookieTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie');
        $cookieStoreTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_store');
        $inserted = 0;
        $updated = 0;

        $connection->beginTransaction();
        try {
            $groupIds = [];
            foreach ($this->defaultCatalog->groups() as $group) {
                $groupId = (int)$connection->fetchOne(
                    $connection->select()->from($groupTable, ['group_id'])->where('code = ?', $group['code'])
                );
                $data = [
                    'code' => $group['code'],
                    'type' => $group['type'],
                    'is_required' => $group['required'],
                    'is_active' => 1,
                    'priority' => $group['priority'],
                ];
                if ($groupId === 0) {
                    $connection->insert($groupTable, $data);
                    $groupId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
                    $inserted++;
                } else {
                    $connection->update($groupTable, $data, ['group_id = ?' => $groupId]);
                    $updated++;
                }
                $connection->insertOnDuplicate($groupStoreTable, [
                    'group_id' => $groupId,
                    'store_id' => $storeId,
                    'name' => $group['name'],
                    'description' => $group['description'],
                ], ['name', 'description']);
                $groupIds[$group['code']] = $groupId;
            }

            foreach ($this->defaultCatalog->storage() as $cookie) {
                $cookieId = (int)$connection->fetchOne(
                    $connection->select()
                        ->from($cookieTable, ['cookie_id'])
                        ->where('code_pattern = ?', $cookie['pattern'])
                        ->where('storage_type = ?', $cookie['type'])
                );
                $data = [
                    'group_id' => $groupIds['essential'],
                    'name' => $cookie['name'],
                    'code_pattern' => $cookie['pattern'],
                    'storage_type' => $cookie['type'],
                    'lifetime' => $cookie['lifetime'],
                    'is_active' => 1,
                ];
                if ($cookieId === 0) {
                    $connection->insert($cookieTable, $data);
                    $cookieId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
                    $inserted++;
                } else {
                    $connection->update($cookieTable, $data, ['cookie_id = ?' => $cookieId]);
                    $updated++;
                }
                $connection->insertOnDuplicate($cookieStoreTable, [
                    'cookie_id' => $cookieId,
                    'store_id' => $storeId,
                    'description' => $cookie['description'],
                    'is_active' => 1,
                ], ['description', 'is_active']);
            }
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $this->invalidateFrontendCache();
        $policy = $this->policyVersionProvider->getOrPublishCurrent($storeId);
        return ['inserted' => $inserted, 'updated' => $updated, 'policy_version' => $policy['version']];
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed>|null $actual
     * @return array{kind:string,key:string,status:string,differences:string[]}
     */
    private function compare(string $kind, string $key, array $expected, ?array $actual): array
    {
        if ($actual === null) {
            return ['kind' => $kind, 'key' => $key, 'status' => 'missing', 'differences' => ['missing']];
        }

        $differences = [];
        foreach ($expected as $field => $value) {
            if (!array_key_exists($field, $actual) || $this->normalize($actual[$field]) !== $this->normalize($value)) {
                $differences[] = $field;
            }
        }

        return [
            'kind' => $kind,
            'key' => $key,
            'status' => $differences === [] ? 'ok' : 'modified',
            'differences' => $differences,
        ];
    }

    private function normalize(mixed $value): string
    {
        return $value === null ? '<null>' : trim((string)$value);
    }

    private function invalidateFrontendCache(): void
    {
        $this->cacheTypeList->cleanType('block_html');
        $this->cacheTypeList->cleanType('full_page');
    }
}
