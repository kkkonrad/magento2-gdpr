<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Export;

use DomainException;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Ramsey\Uuid\Uuid;
use ZipArchive;

class ExportGenerationProcessor implements JobProcessorInterface
{
    public const TYPE = 'export.generate';
    private const SCHEMA_VERSION = '1.0';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DirectoryList $directoryList,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RequestManagementInterface $requestManagement
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function process(JobContext $context): void
    {
        $customerId = (int)($context->payload['customer_id'] ?? 0);
        if ($customerId <= 0 || $context->requestId === null) {
            throw new DomainException('Export job has no customer or request reference.');
        }
        $this->requestManagement->transition(
            $context->requestId,
            RequestStatus::PROCESSING,
            'system',
            null,
            (string)__('Your data export is being generated.')
        );

        $relativeDirectory = 'kkkonrad/gdpr/exports';
        $absoluteDirectory = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/' . $relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0700, true) && !is_dir($absoluteDirectory)) {
            throw new DomainException('Private export directory could not be created.');
        }
        $baseName = Uuid::uuid4()->toString();
        $zipPath = $absoluteDirectory . '/' . $baseName . '.zip';
        $temporaryFiles = [];
        try {
            $datasets = $this->getDatasets($customerId);
            foreach ($datasets as $name => $dataset) {
                $path = $absoluteDirectory . '/' . $baseName . '-' . $name . '.csv';
                $this->writeCsv($path, $dataset['columns'], $dataset['rows']);
                $temporaryFiles[$name . '.csv'] = $path;
            }
            $manifestPath = $absoluteDirectory . '/' . $baseName . '-manifest.json';
            file_put_contents($manifestPath, json_encode([
                'schema_version' => self::SCHEMA_VERSION,
                'generated_at' => gmdate(DATE_ATOM),
                'files' => array_keys($temporaryFiles),
            ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            chmod($manifestPath, 0600);
            $temporaryFiles['manifest.json'] = $manifestPath;

            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::EXCL) !== true) {
                throw new DomainException('Private export archive could not be created.');
            }
            foreach ($temporaryFiles as $archiveName => $path) {
                $zip->addFile($path, $archiveName);
            }
            $zip->close();
            chmod($zipPath, 0600);
        } finally {
            foreach ($temporaryFiles as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }

        $ttlHours = max(1, (int)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/data_rights/export_ttl_hours',
            ScopeInterface::SCOPE_STORE,
            $context->storeId
        ));
        $exportTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_export');
        $this->resourceConnection->getConnection()->insert($exportTable, [
            'request_id' => $context->requestId,
            'storage_path' => $relativeDirectory . '/' . basename($zipPath),
            'schema_version' => self::SCHEMA_VERSION,
            'checksum' => hash_file('sha256', $zipPath),
            'file_size' => filesize($zipPath) ?: 0,
            'expires_at' => gmdate('Y-m-d H:i:s', time() + ($ttlHours * 3600)),
        ]);
        $this->requestManagement->transition(
            $context->requestId,
            RequestStatus::COMPLETED,
            'system',
            null,
            (string)__('Your data export is ready to download.')
        );
    }

    /**
     * @return array<string, array{columns:string[], rows:array<int, array<string, mixed>>}>
     */
    private function getDatasets(int $customerId): array
    {
        $connection = $this->resourceConnection->getConnection();
        $customerTable = $this->resourceConnection->getTableName('customer_entity');
        $addressTable = $this->resourceConnection->getTableName('customer_address_entity');
        $orderTable = $this->resourceConnection->getTableName('sales_order');
        $itemTable = $this->resourceConnection->getTableName('sales_order_item');
        $consentEvent = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_event');
        $consentVersion = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_version');
        $newsletter = $this->resourceConnection->getTableName('newsletter_subscriber');

        $customerColumns = [
            'entity_id', 'website_id', 'store_id', 'email', 'prefix', 'firstname', 'middlename', 'lastname',
            'suffix', 'dob', 'taxvat', 'gender', 'created_at', 'updated_at', 'is_active',
        ];
        $addressColumns = [
            'entity_id', 'parent_id', 'company', 'prefix', 'firstname', 'middlename', 'lastname', 'suffix',
            'street', 'city', 'region', 'region_id', 'postcode', 'country_id', 'telephone', 'fax', 'vat_id',
            'created_at', 'updated_at',
        ];
        $orderColumns = [
            'entity_id', 'increment_id', 'store_id', 'state', 'status', 'created_at', 'updated_at',
            'order_currency_code', 'subtotal', 'shipping_amount', 'tax_amount', 'discount_amount', 'grand_total',
            'total_qty_ordered', 'shipping_description', 'customer_email', 'customer_firstname',
            'customer_lastname', 'customer_note',
        ];
        $itemColumns = [
            'item_id', 'order_id', 'sku', 'name', 'product_type', 'qty_ordered', 'qty_invoiced', 'qty_shipped',
            'qty_refunded', 'price', 'tax_amount', 'discount_amount', 'row_total', 'created_at',
        ];
        $customer = $connection->fetchAll(
            $connection->select()->from($customerTable, $customerColumns)->where('entity_id = ?', $customerId)
        );
        $addresses = $connection->fetchAll(
            $connection->select()->from($addressTable, $addressColumns)->where('parent_id = ?', $customerId)
        );
        $orders = $connection->fetchAll(
            $connection->select()->from($orderTable, $orderColumns)->where('customer_id = ?', $customerId)
        );
        $orderIds = array_map(static fn (array $row): int => (int)$row['entity_id'], $orders);
        $items = $orderIds === [] ? [] : $connection->fetchAll(
            $connection->select()->from($itemTable, $itemColumns)->where('order_id IN (?)', $orderIds)
        );
        $consentColumns = ['event_id', 'decision', 'source', 'store_id', 'occurred_at', 'content_hash', 'content_snapshot'];
        $consents = $connection->fetchAll(
            $connection->select()
                ->from(['event' => $consentEvent], ['event_id', 'decision', 'source', 'store_id', 'occurred_at'])
                ->joinInner(['version' => $consentVersion], 'version.version_id = event.version_id', [
                    'content_hash', 'content_snapshot',
                ])
                ->where('event.customer_id = ?', $customerId)
        );
        $newsletterColumns = ['subscriber_id', 'store_id', 'subscriber_email', 'subscriber_status', 'change_status_at'];
        $subscriptions = $connection->fetchAll(
            $connection->select()->from($newsletter, $newsletterColumns)->where('customer_id = ?', $customerId)
        );

        return [
            'customer' => ['columns' => $customerColumns, 'rows' => $customer],
            'addresses' => ['columns' => $addressColumns, 'rows' => $addresses],
            'orders' => ['columns' => $orderColumns, 'rows' => $orders],
            'order-items' => ['columns' => $itemColumns, 'rows' => $items],
            'consents' => ['columns' => $consentColumns, 'rows' => $consents],
            'newsletter' => ['columns' => $newsletterColumns, 'rows' => $subscriptions],
        ];
    }

    /**
     * @param string[] $columns
     * @param array<int, array<string, mixed>> $rows
     */
    private function writeCsv(string $path, array $columns, array $rows): void
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new DomainException('Private export part could not be created.');
        }
        chmod($path, 0600);
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, $columns, ',', '"', '\\');
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $value = (string)($row[$column] ?? '');
                if (preg_match('/^[=+\-@]/', $value) === 1) {
                    $value = "'" . $value;
                }
                $values[] = $value;
            }
            fputcsv($handle, $values, ',', '"', '\\');
        }
        fclose($handle);
    }
}
