<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Erasure;

use Kkkonrad\Gdpr\Api\DataRights\PersonalDataEraserInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;

class MagentoCoreDataEraser implements PersonalDataEraserInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SecureAreaExecutor $secureAreaExecutor
    ) {
    }

    public function getCode(): string
    {
        return 'magento_core';
    }

    public function getPriority(): int
    {
        return 1000;
    }

    public function erase(int $customerId, string $operationKey): array
    {
        unset($operationKey);
        $connection = $this->resourceConnection->getConnection();
        $counts = [];
        foreach ([
            'vault_payment_token',
            'wishlist',
            'quote',
            'newsletter_subscriber',
            'oauth_token',
            'persistent_session',
            'customer_visitor',
        ] as $tableName) {
            $table = $this->resourceConnection->getTableName($tableName);
            $counts[$tableName] = $connection->isTableExists($table)
                ? $connection->delete($table, ['customer_id = ?' => $customerId])
                : 0;
        }
        $reviewTable = $this->resourceConnection->getTableName('review_detail');
        $counts['reviews'] = $connection->isTableExists($reviewTable)
            ? $connection->update($reviewTable, [
                'customer_id' => null,
                'nickname' => 'Anonymous',
            ], ['customer_id = ?' => $customerId])
            : 0;
        try {
            $this->secureAreaExecutor->execute(fn (): bool => $this->customerRepository->deleteById($customerId));
            $counts['customer_deleted'] = 1;
        } catch (NoSuchEntityException) {
            $counts['customer_deleted'] = 0;
        }
        return $counts;
    }
}
