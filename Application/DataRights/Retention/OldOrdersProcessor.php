<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Retention;

use Kkkonrad\Gdpr\Domain\DataRights\Anonymization\PseudonymGenerator;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorInterface;
use Magento\Framework\App\ResourceConnection;

class OldOrdersProcessor implements JobProcessorInterface
{
    public const TYPE = 'retention.old_orders';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly PseudonymGenerator $pseudonymGenerator
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function process(JobContext $context): void
    {
        $cutoff = (string)($context->payload['cutoff'] ?? '');
        $batchSize = max(1, min(1000, (int)($context->payload['batch_size'] ?? 100)));
        $statuses = $context->payload['statuses'] ?? ['complete', 'closed', 'canceled'];
        if ($cutoff === '' || !is_array($statuses)) {
            throw new \DomainException('Old-order retention configuration is invalid.');
        }
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $addressTable = $this->resourceConnection->getTableName('sales_order_address');
        $orderIds = array_map('intval', $connection->fetchCol(
            $connection->select()
                ->from($orderTable, ['entity_id'])
                ->where('store_id = ?', $context->storeId)
                ->where('created_at < ?', $cutoff)
                ->where('status IN (?)', $statuses)
                ->where('customer_email NOT LIKE ?', 'anon-%@example.invalid')
                ->order('entity_id ASC')
                ->limit($batchSize)
        ));
        foreach ($orderIds as $orderId) {
            $token = $this->pseudonymGenerator->token($context->publicId, 'retention_order', $orderId, 12);
            $connection->update($orderTable, [
                'customer_id' => null,
                'customer_email' => $this->pseudonymGenerator->email($context->publicId, 'retention_order', $orderId),
                'customer_prefix' => null,
                'customer_firstname' => 'Anonymous',
                'customer_middlename' => null,
                'customer_lastname' => $token,
                'customer_suffix' => null,
                'customer_dob' => null,
                'customer_taxvat' => null,
                'customer_note' => null,
                'remote_ip' => null,
                'x_forwarded_for' => null,
            ], ['entity_id = ?' => $orderId]);
            $addressIds = array_map('intval', $connection->fetchCol(
                $connection->select()->from($addressTable, ['entity_id'])->where('parent_id = ?', $orderId)
            ));
            foreach ($addressIds as $addressId) {
                $data = $this->pseudonymGenerator->address($context->publicId, 'retention_order_address', $addressId);
                $data['email'] = $this->pseudonymGenerator->email(
                    $context->publicId,
                    'retention_order_address',
                    $addressId
                );
                $data['customer_id'] = null;
                $data['customer_address_id'] = null;
                $connection->update($addressTable, $data, ['entity_id = ?' => $addressId]);
            }
        }
    }
}
