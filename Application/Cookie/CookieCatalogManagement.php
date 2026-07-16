<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Cookie;

use DomainException;
use Kkkonrad\Gdpr\Domain\Cookie\CookiePatternMatcher;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class CookieCatalogManagement
{
    private const GROUP_TYPES = ['essential', 'functionality', 'statistical', 'marketing', 'custom'];
    private const STORAGE_TYPES = ['cookie', 'local_storage', 'session_storage'];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CookiePatternMatcher $patternMatcher
    ) {
    }

    public function saveGroup(
        ?int $groupId,
        string $code,
        string $type,
        bool $required,
        bool $active,
        int $priority,
        int $storeId,
        string $name,
        string $description
    ): int {
        if (preg_match('/^[a-z][a-z0-9_.-]{2,63}$/', $code) !== 1
            || !in_array($type, self::GROUP_TYPES, true)
            || trim($name) === ''
        ) {
            throw new DomainException('Cookie group data is invalid.');
        }
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_group');
        $storeTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_group_store');
        $connection->beginTransaction();
        try {
            $data = [
                'code' => $code,
                'type' => $type,
                'is_required' => (int)$required,
                'is_active' => (int)$active,
                'priority' => max(0, $priority),
            ];
            if ($groupId === null) {
                $connection->insert($table, $data);
                $groupId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
            } else {
                $connection->update($table, $data, ['group_id = ?' => $groupId]);
            }
            $connection->insertOnDuplicate($storeTable, [
                'group_id' => $groupId,
                'store_id' => $storeId,
                'name' => trim($name),
                'description' => trim($description),
            ], ['name', 'description']);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $groupId;
    }

    public function saveCookie(
        ?int $cookieId,
        int $groupId,
        string $name,
        string $pattern,
        string $storageType,
        ?int $lifetime,
        bool $active,
        int $storeId,
        string $description,
        bool $activeInStore
    ): int {
        $this->patternMatcher->assertValid($pattern);
        if (trim($name) === '' || !in_array($storageType, self::STORAGE_TYPES, true)) {
            throw new DomainException('Cookie definition data is invalid.');
        }
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie');
        $storeTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_store');
        $connection->beginTransaction();
        try {
            $patterns = $connection->fetchCol(
                $connection->select()
                    ->from($table, ['code_pattern'])
                    ->where('storage_type = ?', $storageType)
                    ->where('cookie_id != ?', $cookieId ?? 0)
            );
            $this->patternMatcher->assertNoConflicts(array_merge(array_map('strval', $patterns), [$pattern]));
            $data = [
                'group_id' => $groupId,
                'name' => trim($name),
                'code_pattern' => $pattern,
                'storage_type' => $storageType,
                'lifetime' => $lifetime !== null ? max(0, $lifetime) : null,
                'is_active' => (int)$active,
            ];
            if ($cookieId === null) {
                $connection->insert($table, $data);
                $cookieId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
            } else {
                $connection->update($table, $data, ['cookie_id = ?' => $cookieId]);
            }
            $connection->insertOnDuplicate($storeTable, [
                'cookie_id' => $cookieId,
                'store_id' => $storeId,
                'description' => trim($description),
                'is_active' => (int)$activeInStore,
            ], ['description', 'is_active']);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        return $cookieId;
    }
}
