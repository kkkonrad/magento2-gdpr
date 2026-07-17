<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Retention;

use Magento\Framework\App\ResourceConnection;

class AbandonedAccountActivityPolicy
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function isStillInactive(int $customerId, int $storeId, string $cutoff, string $reference): bool
    {
        if ($customerId <= 0 || strtotime($cutoff) === false) {
            return false;
        }
        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $customer = $connection->fetchRow(
            $connection->select()
                ->from($customerTable, ['created_at', 'updated_at', 'store_id'])
                ->where('entity_id = ?', $customerId)
        );
        if ($customer === false || (int)$customer['store_id'] !== $storeId) {
            return false;
        }
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $lastOrder = $connection->fetchOne(
            $connection->select()->from($orderTable, ['MAX(created_at)'])->where('customer_id = ?', $customerId)
        );
        $logTable = $this->resourceConnection->getTableName('customer_log');
        $lastLogin = $connection->fetchOne(
            $connection->select()->from($logTable, ['MAX(last_login_at)'])->where('customer_id = ?', $customerId)
        );
        $created = (string)$customer['created_at'];
        $orderActivity = $created;
        if (is_string($lastOrder) && $lastOrder !== '') {
            $orderActivity = $lastOrder;
        }
        $loginActivity = $created;
        if (is_string($lastLogin) && $lastLogin !== '') {
            $loginActivity = $lastLogin;
        }
        $activity = match ($reference) {
            'last_login_or_created' => $loginActivity,
            'latest_activity' => max([
                (string)$customer['updated_at'],
                $orderActivity,
                $loginActivity,
            ]),
            default => $orderActivity,
        };
        return $activity < $cutoff;
    }
}
