<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Source;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Data\OptionSourceInterface;

class AdminRole implements OptionSourceInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    /** @return array<int, array{value:int,label:string}> */
    public function toOptionArray(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('authorization_role');
        return array_map(
            static fn (array $row): array => [
                'value' => (int)$row['role_id'],
                'label' => (string)$row['role_name'],
            ],
            $connection->fetchAll(
                $connection->select()
                    ->from($table, ['role_id', 'role_name'])
                    ->where('role_type = ?', 'G')
                    ->order('role_name ASC')
            )
        );
    }
}
