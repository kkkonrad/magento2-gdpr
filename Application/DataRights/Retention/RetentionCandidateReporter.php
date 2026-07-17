<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Retention;

use Kkkonrad\Gdpr\Api\ClockInterface;
use Kkkonrad\Gdpr\Api\ConfigProviderInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;

class RetentionCandidateReporter
{
    public function __construct(
        private readonly FeatureManagerInterface $featureManager,
        private readonly ConfigProviderInterface $configProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly ClockInterface $clock
    ) {
    }

    /**
     * @param int[]|null $storeIds
     * @return array<int, array{store_id:int,old_orders:int,old_order_samples:int[],abandoned_accounts:int,account_samples:int[]}>
     */
    public function report(?array $storeIds = null): array
    {
        $filter = $storeIds === null ? null : array_fill_keys(array_map('intval', $storeIds), true);
        $result = [];
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int)$store->getId();
            if ($filter !== null && !isset($filter[$storeId])) {
                continue;
            }
            [$oldCount, $oldSamples] = $this->oldOrders($storeId);
            [$accountCount, $accountSamples] = $this->abandonedAccounts($storeId);
            $result[] = [
                'store_id' => $storeId,
                'old_orders' => $oldCount,
                'old_order_samples' => $oldSamples,
                'abandoned_accounts' => $accountCount,
                'account_samples' => $accountSamples,
            ];
        }
        return $result;
    }

    /** @return array{int, int[]} */
    private function oldOrders(int $storeId): array
    {
        if (!$this->featureManager->isEnabled(FeatureCode::RETENTION_OLD_ORDERS, $storeId)) {
            return [0, []];
        }
        $days = $this->configProvider->getPositiveInt(
            'kkkonrad_gdpr/data_rights/retention_old_orders_days',
            $storeId,
            1825
        );
        $statuses = $this->configProvider->getCsv(
            'kkkonrad_gdpr/data_rights/retention_order_statuses',
            $storeId
        ) ?: ['complete', 'closed', 'canceled'];
        $table = $this->resourceConnection->getTableName('sales_order');
        $connection = $this->resourceConnection->getConnection();
        $base = $connection->select()
            ->from($table, ['entity_id'])
            ->where('store_id = ?', $storeId)
            ->where('created_at < ?', $this->clock->now()->modify('-' . $days . ' days')->format('Y-m-d H:i:s'))
            ->where('status IN (?)', $statuses)
            ->where('customer_email NOT LIKE ?', 'anon-%@example.invalid');
        $count = (int)$connection->fetchOne(
            $connection->select()->from(['candidate' => $base], ['COUNT(*)'])
        );
        $samples = array_map('intval', $connection->fetchCol(
            clone $base->order('entity_id ASC')->limit(10)
        ));
        return [$count, $samples];
    }

    /** @return array{int, int[]} */
    private function abandonedAccounts(int $storeId): array
    {
        if (!$this->featureManager->isEnabled(FeatureCode::RETENTION_ABANDONED_ACCOUNTS, $storeId)) {
            return [0, []];
        }
        $days = $this->configProvider->getPositiveInt(
            'kkkonrad_gdpr/data_rights/retention_abandoned_accounts_days',
            $storeId,
            1825
        );
        $reference = $this->configProvider->getString(
            'kkkonrad_gdpr/data_rights/retention_abandoned_reference',
            $storeId
        );
        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $customerLogTable = $this->resourceConnection->getTableName('customer_log');
        $lastOrder = $connection->select()
            ->from($orderTable, ['customer_id', 'last_order_at' => 'MAX(created_at)'])
            ->where('customer_id IS NOT NULL')
            ->group('customer_id');
        $lastLogin = $connection->select()
            ->from($customerLogTable, ['customer_id', 'last_login_at' => 'MAX(last_login_at)'])
            ->group('customer_id');
        $referenceExpression = match ($reference) {
            'last_login_or_created' => 'COALESCE(login.last_login_at, customer.created_at)',
            'latest_activity' => 'GREATEST(customer.updated_at, '
                . 'COALESCE(orders.last_order_at, customer.created_at), '
                . 'COALESCE(login.last_login_at, customer.created_at))',
            default => 'COALESCE(orders.last_order_at, customer.created_at)',
        };
        $base = $connection->select()
            ->from(['customer' => $customerTable], ['entity_id'])
            ->joinLeft(['orders' => $lastOrder], 'orders.customer_id = customer.entity_id', [])
            ->joinLeft(['login' => $lastLogin], 'login.customer_id = customer.entity_id', [])
            ->where('customer.store_id = ?', $storeId)
            ->where(
                $referenceExpression . ' < ?',
                $this->clock->now()->modify('-' . $days . ' days')->format('Y-m-d H:i:s')
            )
            ->where('customer.email NOT LIKE ?', 'anon-%@example.invalid');
        $count = (int)$connection->fetchOne(
            $connection->select()->from(['candidate' => $base], ['COUNT(*)'])
        );
        $samples = array_map('intval', $connection->fetchCol(
            clone $base->order('customer.entity_id ASC')->limit(10)
        ));
        return [$count, $samples];
    }
}
