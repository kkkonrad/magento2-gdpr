<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights;

use Kkkonrad\Gdpr\Api\ClockInterface;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Api\DataRights\LegalHoldProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;

class EligibilityPolicy
{
    /** @var LegalHoldProviderInterface[] */
    private array $legalHoldProviders;

    /** @param LegalHoldProviderInterface[] $legalHoldProviders */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ClockInterface $clock,
        array $legalHoldProviders = []
    ) {
        $this->legalHoldProviders = $legalHoldProviders;
    }

    /**
     * @return array{eligible:bool, code:string, message:string}
     */
    public function evaluate(int $customerId, string $type, int $storeId): array
    {
        if ($type === RequestType::EXPORT) {
            return ['eligible' => true, 'code' => 'eligible', 'message' => ''];
        }
        foreach ($this->legalHoldProviders as $provider) {
            $reason = $provider->getBlockReason($customerId, $type, $storeId);
            if ($reason !== null) {
                return [
                    'eligible' => false,
                    'code' => 'legal_hold_' . $provider->getCode(),
                    'message' => $reason,
                ];
            }
        }
        $configured = (string)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/data_rights/anonymization_order_statuses',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $allowedStatuses = array_values(array_filter(array_map('trim', explode(',', $configured))));
        if ($allowedStatuses === []) {
            $allowedStatuses = ['complete', 'closed', 'canceled'];
        }
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $blockingCount = (int)$this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()->select()
                ->from($orderTable, ['COUNT(*)'])
                ->where('customer_id = ?', $customerId)
                ->where('status NOT IN (?)', $allowedStatuses)
        );
        if ($blockingCount > 0) {
            return [
                'eligible' => false,
                'code' => 'open_orders',
                'message' => (string)__('The request cannot be processed while an order is still active.'),
            ];
        }

        $protectionDays = max(1, (int)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/data_rights/document_protection_days',
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $protectedCount = (int)$this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()->select()
                ->from($orderTable, ['COUNT(*)'])
                ->where('customer_id = ?', $customerId)
                ->where(
                    'created_at >= ?',
                    $this->clock->now()->modify('-' . $protectionDays . ' days')->format('Y-m-d H:i:s')
                )
        );
        if ($protectedCount > 0) {
            return [
                'eligible' => false,
                'code' => 'protected_sales_documents',
                'message' => (string)__('The request cannot be processed while sales documents remain protected.'),
            ];
        }

        return ['eligible' => true, 'code' => 'eligible', 'message' => ''];
    }
}
