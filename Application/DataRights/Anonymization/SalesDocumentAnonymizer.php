<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Anonymization;

use Kkkonrad\Gdpr\Api\DataRights\PersonalDataAnonymizerInterface;
use Magento\Framework\App\ResourceConnection;

class SalesDocumentAnonymizer implements PersonalDataAnonymizerInterface
{
    public function __construct(private readonly ResourceConnection $resourceConnection)
    {
    }

    public function getCode(): string
    {
        return 'sales_documents';
    }

    public function getPriority(): int
    {
        return 10;
    }

    public function anonymize(int $customerId, string $operationKey): array
    {
        unset($operationKey);
        $connection = $this->resourceConnection->getConnection();
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $orderIds = array_map('intval', $connection->fetchCol(
            $connection->select()->from($orderTable, ['entity_id'])->where('customer_id = ?', $customerId)
        ));
        if ($orderIds === []) {
            return ['invoices' => 0, 'shipments' => 0, 'creditmemos' => 0];
        }

        $invoiceTable = $this->resourceConnection->getTableName('sales_invoice');
        $shipmentTable = $this->resourceConnection->getTableName('sales_shipment');
        $creditmemoTable = $this->resourceConnection->getTableName('sales_creditmemo');
        $counts = [
            'invoices' => $connection->update($invoiceTable, [
                'customer_note' => null,
                'customer_note_notify' => 0,
            ], ['order_id IN (?)' => $orderIds]),
            'shipments' => $connection->update($shipmentTable, [
                'customer_id' => null,
                'customer_note' => null,
                'customer_note_notify' => 0,
                'packages' => null,
                'shipping_label' => null,
            ], ['order_id IN (?)' => $orderIds]),
            'creditmemos' => $connection->update($creditmemoTable, [
                'customer_note' => null,
                'customer_note_notify' => 0,
            ], ['order_id IN (?)' => $orderIds]),
        ];

        foreach ([
            'sales_invoice_comment' => $invoiceTable,
            'sales_shipment_comment' => $shipmentTable,
            'sales_creditmemo_comment' => $creditmemoTable,
        ] as $commentTableName => $parentTable) {
            $parentIds = array_map('intval', $connection->fetchCol(
                $connection->select()->from($parentTable, ['entity_id'])->where('order_id IN (?)', $orderIds)
            ));
            $counter = str_replace('sales_', '', $commentTableName) . 's';
            $counts[$counter] = $parentIds === [] ? 0 : $connection->update(
                $this->resourceConnection->getTableName($commentTableName),
                ['comment' => null],
                ['parent_id IN (?)' => $parentIds, 'is_visible_on_front = ?' => 1]
            );
        }

        return $counts;
    }
}
