<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Cron;

use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\JobSchedulerInterface;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\Anonymization\AnonymizationProcessor;
use Kkkonrad\Gdpr\Application\DataRights\Retention\OldOrdersProcessor;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class ScheduleRetention
{
    public function __construct(
        private readonly FeatureManagerInterface $featureManager,
        private readonly JobSchedulerInterface $jobScheduler,
        private readonly RequestManagementInterface $requestManagement,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly StoreManagerInterface $storeManager,
        private readonly ResourceConnection $resourceConnection
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
                $days = max(1, (int)$this->scopeConfig->getValue(
                    'kkkonrad_gdpr/data_rights/retention_old_orders_days',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                ));
                $configuredStatuses = (string)$this->scopeConfig->getValue(
                    'kkkonrad_gdpr/data_rights/anonymization_order_statuses',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                );
                $statuses = array_values(array_filter(array_map('trim', explode(',', $configuredStatuses))));
                $this->jobScheduler->schedule(
                    OldOrdersProcessor::TYPE,
                    FeatureCode::RETENTION_OLD_ORDERS,
                    $storeId,
                    [
                        'cutoff' => gmdate('Y-m-d H:i:s', time() - ($days * 86400)),
                        'statuses' => $statuses ?: ['complete', 'closed', 'canceled'],
                        'batch_size' => $batchSize,
                    ],
                    null,
                    'retention-old-orders-' . $storeId . '-' . gmdate('Y-m-d')
                );
            }
            if ($this->featureManager->isEnabled(FeatureCode::RETENTION_ABANDONED_ACCOUNTS, $storeId)) {
                $this->scheduleAbandonedAccounts($storeId, $batchSize);
            }
        }
    }

    private function scheduleAbandonedAccounts(int $storeId, int $batchSize): void
    {
        $days = max(1, (int)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/data_rights/retention_abandoned_accounts_days',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $customerIds = array_map('intval', $this->resourceConnection->getConnection()->fetchCol(
            $this->resourceConnection->getConnection()->select()
                ->from($customerTable, ['entity_id'])
                ->where('store_id = ?', $storeId)
                ->where('updated_at < ?', gmdate('Y-m-d H:i:s', time() - ($days * 86400)))
                ->where('email NOT LIKE ?', 'anon-%@example.invalid')
                ->order('entity_id ASC')
                ->limit($batchSize)
        ));
        foreach ($customerIds as $customerId) {
            try {
                $requestId = $this->requestManagement->submit($customerId, RequestType::ANONYMIZE, $storeId);
                $this->requestManagement->transition($requestId, RequestStatus::VALIDATION, 'system');
                $this->requestManagement->transition($requestId, RequestStatus::QUEUED, 'system');
                $this->jobScheduler->schedule(
                    AnonymizationProcessor::TYPE,
                    FeatureCode::RETENTION_ABANDONED_ACCOUNTS,
                    $storeId,
                    ['customer_id' => $customerId],
                    $requestId,
                    'retention-account-' . $customerId
                );
            } catch (Throwable) {
                // An active request or a concurrently removed account is skipped until the next run.
                continue;
            }
        }
    }
}
