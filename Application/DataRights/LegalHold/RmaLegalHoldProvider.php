<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\LegalHold;

use Kkkonrad\Gdpr\Api\DataRights\LegalHoldProviderInterface;
use Magento\Framework\App\ResourceConnection;

class RmaLegalHoldProvider implements LegalHoldProviderInterface
{
    private const TERMINAL_STATUSES = ['rejected', 'resolved', 'closed', 'cancelled'];

    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getCode(): string
    {
        return 'kkkonrad_rma';
    }

    public function getBlockReason(int $customerId, string $operation, int $storeId): ?string
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('kkkonrad_rma');
        if (!$connection->isTableExists($table)) {
            return null;
        }
        $count = (int)$connection->fetchOne(
            $connection->select()
                ->from($table, ['COUNT(*)'])
                ->where('customer_id = ?', $customerId)
                ->where('status NOT IN (?)', self::TERMINAL_STATUSES)
        );

        return $count > 0
            ? (string)__('The request cannot be processed while a return or complaint is still active.')
            : null;
    }
}
