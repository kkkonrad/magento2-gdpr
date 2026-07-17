<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Cron;

use Kkkonrad\Gdpr\Api\ClockInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\JobSchedulerInterface;
use Kkkonrad\Gdpr\Application\DataRights\Retention\AbandonedAccountsProcessor;
use Kkkonrad\Gdpr\Application\DataRights\Retention\OldOrdersProcessor;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class ScheduleRetention
{
    public function __construct(
        private readonly FeatureManagerInterface $featureManager,
        private readonly JobSchedulerInterface $jobScheduler,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection,
        private readonly ClockInterface $clock
    ) {
    }

    public function execute(): void
    {
        foreach ($this->storeManager->getStores() as $store) {
            $storeId = (int)$store->getId();
            $batchSize = max(1, (int)$this->scopeConfig->getValue(
                'kkkonrad_gdpr/data_rights/batch_size',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ));
            if ($this->featureManager->isEnabled(FeatureCode::RETENTION_OLD_ORDERS, $storeId)) {
                $this->scheduleOldOrders($storeId, $batchSize);
            }
            if ($this->featureManager->isEnabled(FeatureCode::RETENTION_ABANDONED_ACCOUNTS, $storeId)) {
                $this->scheduleAbandonedAccounts($storeId, $batchSize);
            }
        }
    }

    private function scheduleOldOrders(int $storeId, int $batchSize): void
    {
        $days = max(1, (int)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/data_rights/retention_old_orders_days',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $configuredStatuses = (string)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/data_rights/retention_order_statuses',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $statuses = array_values(array_filter(array_map('trim', explode(',', $configuredStatuses))));
        $this->jobScheduler->schedule(
            OldOrdersProcessor::TYPE,
            FeatureCode::RETENTION_OLD_ORDERS,
            $storeId,
            [
                'cutoff' => $this->clock->now()->modify('-' . $days . ' days')->format('Y-m-d H:i:s'),
                'statuses' => $statuses ?: ['complete', 'closed', 'canceled'],
                'batch_size' => $batchSize,
                'cursor' => $this->cursor(OldOrdersProcessor::TYPE, $storeId),
            ],
            null,
            'retention-old-orders-' . $storeId . '-' . $this->clock->now()->format('Y-m-d')
        );
    }

    private function scheduleAbandonedAccounts(int $storeId, int $batchSize): void
    {
        $days = max(1, (int)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/data_rights/retention_abandoned_accounts_days',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $action = (string)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/data_rights/retention_abandoned_action',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $action = $action === 'erase' ? 'erase' : 'anonymize';
        $reference = (string)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/data_rights/retention_abandoned_reference',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $warningEnabled = $action === 'erase' && $this->scopeConfig->isSetFlag(
            'kkkonrad_gdpr/data_rights/retention_abandoned_warning_enabled',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $warningDays = $warningEnabled ? max(1, min(365, (int)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/data_rights/retention_abandoned_warning_days',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ))) : 0;
        $this->jobScheduler->schedule(
            AbandonedAccountsProcessor::TYPE,
            FeatureCode::RETENTION_ABANDONED_ACCOUNTS,
            $storeId,
            [
                'cutoff' => $this->clock->now()->modify('-' . $days . ' days')->format('Y-m-d H:i:s'),
                'action' => $action,
                'reference' => $reference,
                'batch_size' => $batchSize,
                'cursor' => $this->cursor(AbandonedAccountsProcessor::TYPE, $storeId),
                'warning_days' => $warningDays,
            ],
            null,
            'retention-abandoned-accounts-' . $storeId . '-' . $this->clock->now()->format('Y-m-d')
        );
    }

    private function cursor(string $type, int $storeId): int
    {
        $jobTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        $checkpoint = $this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()->select()
                ->from($jobTable, ['checkpoint'])
                ->where('type = ?', $type)
                ->where('store_id = ?', $storeId)
                ->where('status IN (?)', ['completed', 'partially_completed'])
                ->where('checkpoint IS NOT NULL')
                ->order('job_id DESC')
                ->limit(1)
        );
        if (!is_string($checkpoint) || str_starts_with($checkpoint, 'exhausted')) {
            return 0;
        }
        return preg_match('/(?:^|;)cursor:(\d+)/', $checkpoint, $match) === 1
            ? (int)$match[1]
            : 0;
    }
}
