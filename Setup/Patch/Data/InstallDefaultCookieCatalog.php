<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Setup\Patch\Data;

use Kkkonrad\Gdpr\Domain\Cookie\DefaultCookieCatalog;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class InstallDefaultCookieCatalog implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly DefaultCookieCatalog $defaultCatalog
    ) {
    }

    public function apply(): self
    {
        $connection = $this->moduleDataSetup->getConnection();
        $groupTable = $this->moduleDataSetup->getTable('kkkonrad_gdpr_cookie_group');
        $groupStoreTable = $this->moduleDataSetup->getTable('kkkonrad_gdpr_cookie_group_store');
        $cookieTable = $this->moduleDataSetup->getTable('kkkonrad_gdpr_cookie');
        $cookieStoreTable = $this->moduleDataSetup->getTable('kkkonrad_gdpr_cookie_store');

        $this->moduleDataSetup->startSetup();
        try {
            foreach ($this->defaultCatalog->groups() as $group) {
                $groupRows = $connection->fetchAll(
                    $connection->select()->from($groupTable, ['group_id'])->where('code = ?', $group['code'])
                );
                if ($groupRows === []) {
                    $connection->insert($groupTable, [
                        'code' => $group['code'],
                        'type' => $group['type'],
                        'is_required' => $group['required'],
                        'is_active' => 1,
                        'priority' => $group['priority'],
                    ]);
                    $groupId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
                } else {
                    $groupId = (int)$groupRows[0]['group_id'];
                }

                $storeRows = $connection->fetchAll(
                    $connection->select()
                        ->from($groupStoreTable, ['group_store_id'])
                        ->where('group_id = ?', (int)$groupId)
                        ->where('store_id = 0')
                );
                if ($storeRows === []) {
                    $connection->insert($groupStoreTable, [
                        'group_id' => (int)$groupId,
                        'store_id' => 0,
                        'name' => $group['name'],
                        'description' => $group['description'],
                    ]);
                }
            }

            $essentialGroupId = (int)$connection->fetchOne(
                $connection->select()->from($groupTable, ['group_id'])->where('code = ?', 'essential')
            );
            foreach ($this->defaultCatalog->storage() as $cookie) {
                $cookieRows = $connection->fetchAll(
                    $connection->select()
                        ->from($cookieTable, ['cookie_id'])
                        ->where('code_pattern = ?', $cookie['pattern'])
                        ->where('storage_type = ?', $cookie['type'])
                );
                if ($cookieRows === []) {
                    $connection->insert($cookieTable, [
                        'group_id' => $essentialGroupId,
                        'name' => $cookie['name'],
                        'code_pattern' => $cookie['pattern'],
                        'storage_type' => $cookie['type'],
                        'lifetime' => $cookie['lifetime'],
                        'is_active' => 1,
                    ]);
                    $cookieId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
                } else {
                    $cookieId = (int)$cookieRows[0]['cookie_id'];
                }

                $storeRows = $connection->fetchAll(
                    $connection->select()
                        ->from($cookieStoreTable, ['cookie_store_id'])
                        ->where('cookie_id = ?', (int)$cookieId)
                        ->where('store_id = 0')
                );
                if ($storeRows === []) {
                    $connection->insert($cookieStoreTable, [
                        'cookie_id' => (int)$cookieId,
                        'store_id' => 0,
                        'description' => $cookie['description'],
                        'is_active' => 1,
                    ]);
                }
            }
        } finally {
            $this->moduleDataSetup->endSetup();
        }

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
