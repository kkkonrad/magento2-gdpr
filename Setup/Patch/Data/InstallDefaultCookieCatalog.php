<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

final class InstallDefaultCookieCatalog implements DataPatchInterface
{
    private const GROUPS = [
        ['code' => 'essential', 'type' => 'essential', 'required' => 1, 'priority' => 10,
            'name' => 'Essential', 'description' => 'Required for security and core store functionality.'],
        ['code' => 'functionality', 'type' => 'functionality', 'required' => 0, 'priority' => 20,
            'name' => 'Functionality', 'description' => 'Used for optional personalization and enhanced storefront features.'],
        ['code' => 'statistical', 'type' => 'statistical', 'required' => 0, 'priority' => 30,
            'name' => 'Statistical', 'description' => 'Used to understand storefront usage and performance.'],
        ['code' => 'marketing', 'type' => 'marketing', 'required' => 0, 'priority' => 40,
            'name' => 'Marketing', 'description' => 'Used for advertising, remarketing and ad personalization.'],
    ];

    private const ESSENTIAL_STORAGE = [
        ['name' => 'PHP session', 'pattern' => 'PHPSESSID', 'type' => 'cookie', 'lifetime' => null,
            'description' => 'Maintains the server-side storefront session.'],
        ['name' => 'Form key', 'pattern' => 'form_key', 'type' => 'cookie', 'lifetime' => null,
            'description' => 'Protects forms against cross-site request forgery.'],
        ['name' => 'Magento vary', 'pattern' => 'X-Magento-Vary', 'type' => 'cookie', 'lifetime' => null,
            'description' => 'Supports correct private content and full-page cache variants.'],
        ['name' => 'Private content version', 'pattern' => 'private_content_version', 'type' => 'cookie', 'lifetime' => null,
            'description' => 'Invalidates customer-specific browser content when required.'],
        ['name' => 'Store view', 'pattern' => 'store', 'type' => 'cookie', 'lifetime' => null,
            'description' => 'Stores the selected store view.'],
        ['name' => 'Login redirect', 'pattern' => 'login_redirect', 'type' => 'cookie', 'lifetime' => null,
            'description' => 'Returns the customer to the intended page after authentication.'],
        ['name' => 'Flash messages', 'pattern' => 'mage-messages', 'type' => 'cookie', 'lifetime' => 10,
            'description' => 'Displays one-time success and error messages.'],
        ['name' => 'Cache session marker', 'pattern' => 'mage-cache-sessid', 'type' => 'cookie', 'lifetime' => null,
            'description' => 'Triggers cleanup of local private-content storage.'],
        ['name' => 'Cache storage', 'pattern' => 'mage-cache-storage', 'type' => 'local_storage', 'lifetime' => null,
            'description' => 'Stores customer-specific storefront sections in the browser.'],
        ['name' => 'Cache invalidation', 'pattern' => 'mage-cache-storage-section-invalidation', 'type' => 'local_storage', 'lifetime' => null,
            'description' => 'Tracks invalidated customer-data sections.'],
        ['name' => 'Section data IDs', 'pattern' => 'section_data_ids', 'type' => 'cookie', 'lifetime' => null,
            'description' => 'Tracks versions of customer-data sections.'],
    ];

    public function __construct(private readonly ModuleDataSetupInterface $moduleDataSetup)
    {
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
            foreach (self::GROUPS as $group) {
                $groupId = $connection->fetchOne(
                    $connection->select()->from($groupTable, ['group_id'])->where('code = ?', $group['code'])
                );
                if ($groupId === false) {
                    $connection->insert($groupTable, [
                        'code' => $group['code'],
                        'type' => $group['type'],
                        'is_required' => $group['required'],
                        'is_active' => 1,
                        'priority' => $group['priority'],
                    ]);
                    $groupId = (int)$connection->lastInsertId($groupTable);
                }

                $storeRowExists = $connection->fetchOne(
                    $connection->select()
                        ->from($groupStoreTable, ['group_store_id'])
                        ->where('group_id = ?', (int)$groupId)
                        ->where('store_id = 0')
                );
                if ($storeRowExists === false) {
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
            foreach (self::ESSENTIAL_STORAGE as $cookie) {
                $cookieId = $connection->fetchOne(
                    $connection->select()
                        ->from($cookieTable, ['cookie_id'])
                        ->where('code_pattern = ?', $cookie['pattern'])
                        ->where('storage_type = ?', $cookie['type'])
                );
                if ($cookieId === false) {
                    $connection->insert($cookieTable, [
                        'group_id' => $essentialGroupId,
                        'name' => $cookie['name'],
                        'code_pattern' => $cookie['pattern'],
                        'storage_type' => $cookie['type'],
                        'lifetime' => $cookie['lifetime'],
                        'is_active' => 1,
                    ]);
                    $cookieId = (int)$connection->lastInsertId($cookieTable);
                }

                $storeRowExists = $connection->fetchOne(
                    $connection->select()
                        ->from($cookieStoreTable, ['cookie_store_id'])
                        ->where('cookie_id = ?', (int)$cookieId)
                        ->where('store_id = 0')
                );
                if ($storeRowExists === false) {
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
