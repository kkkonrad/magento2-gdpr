<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Export;

use DomainException;
use Kkkonrad\Gdpr\Api\ClockInterface;
use Kkkonrad\Gdpr\Api\RandomIdGeneratorInterface;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\Notification\RequestNotification;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;
use Throwable;
use ZipArchive;
use Kkkonrad\Gdpr\Application\Audit\AuditWriter;

class ExportGenerationProcessor implements JobProcessorInterface
{
    public const TYPE = 'export.generate';
    private const SCHEMA_VERSION = '1.0';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DirectoryList $directoryList,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly RequestManagementInterface $requestManagement,
        private readonly ClockInterface $clock,
        private readonly RandomIdGeneratorInterface $randomIdGenerator,
        private readonly ExporterPool $exporterPool,
        private readonly RequestNotification $requestNotification,
        private readonly LoggerInterface $logger,
        private readonly AuditWriter $auditWriter
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
        $requestStatus = $this->beginOrResumeRequest($context->requestId);
        if ($requestStatus === RequestStatus::COMPLETED) {
            $this->notify($context->requestId, 'completed');
            return;
        }

        $zipPath = null;
        try {
        $relativeDirectory = 'kkkonrad/gdpr/exports';
        $absoluteDirectory = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/' . $relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0700, true) && !is_dir($absoluteDirectory)) {
            throw new DomainException('Private export directory could not be created.');
        }
        $baseName = $this->randomIdGenerator->uuid();
        $zipPath = $absoluteDirectory . '/' . $baseName . '.zip';
        $temporaryFiles = [];
        try {
            $datasets = $this->collectDatasets($customerId, $context->storeId);
            foreach ($datasets as $name => $dataset) {
                $path = $absoluteDirectory . '/' . $baseName . '-' . $name . '.csv';
                $this->writeCsv($path, $dataset['columns'], $dataset['rows']);
                $temporaryFiles[$name . '.csv'] = $path;
            }
            $manifestPath = $absoluteDirectory . '/' . $baseName . '-manifest.json';
            file_put_contents($manifestPath, json_encode([
                'schema_version' => self::SCHEMA_VERSION,
                'generated_at' => $this->clock->now()->format(DATE_ATOM),
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
        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $connection->insert($exportTable, [
                'request_id' => $context->requestId,
                'storage_path' => $relativeDirectory . '/' . basename($zipPath),
                'schema_version' => self::SCHEMA_VERSION,
                'checksum' => hash_file('sha256', $zipPath),
                'file_size' => filesize($zipPath) ?: 0,
                'expires_at' => $this->clock->now()->modify('+' . $ttlHours . ' hours')->format('Y-m-d H:i:s'),
            ]);
            $exportId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
            $this->auditWriter->write(
                'export.generated',
                'export',
                (string)$exportId,
                'system',
                null,
                $context->storeId,
                $context->publicId,
                [
                    'request_id' => $context->requestId,
                    'schema_version' => self::SCHEMA_VERSION,
                    'file_size' => filesize($zipPath) ?: 0,
                ]
            );
            $this->requestManagement->transition(
                $context->requestId,
                RequestStatus::COMPLETED,
                'system',
                null,
                (string)__('Your data export is ready to download.')
            );
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        $this->notify($context->requestId, 'completed');
        } catch (Throwable $exception) {
            if (is_string($zipPath) && is_file($zipPath)) {
                unlink($zipPath);
            }
            $this->requestManagement->transition(
                $context->requestId,
                RequestStatus::FAILED,
                'system',
                null,
                (string)__('The data export could not be generated and requires review.'),
                null,
                ['error_code' => 'export_failed']
            );
            $this->notify($context->requestId, 'failed');
            throw $exception;
        }
    }

    private function notify(int $requestId, string $event): void
    {
        try {
            $this->requestNotification->prepare($requestId, $event);
        } catch (Throwable) {
            $this->logger->warning('A GDPR export notification could not be queued.', [
                'request_id' => $requestId,
                'notification_event' => $event,
                'error_code' => 'notification_prepare_failed',
            ]);
        }
    }

    private function beginOrResumeRequest(int $requestId): string
    {
        $requestTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $status = $this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()->select()
                ->from($requestTable, ['status'])
                ->where('request_id = ?', $requestId)
        );
        if (!is_string($status)) {
            throw new DomainException('The export request no longer exists.');
        }
        if ($status === RequestStatus::QUEUED) {
            $this->requestManagement->transition(
                $requestId,
                RequestStatus::PROCESSING,
                'system',
                null,
                (string)__('Your data export is being generated.')
            );
            return RequestStatus::PROCESSING;
        }
        if (!in_array($status, [RequestStatus::PROCESSING, RequestStatus::COMPLETED], true)) {
            throw new DomainException(sprintf('The export request cannot resume from status "%s".', $status));
        }

        return $status;
    }

    /**
     * @return array<string, array{columns:string[], rows:array<int, array<string, mixed>>}>
     */
    private function collectDatasets(int $customerId, int $storeId): array
    {
        $datasets = [];
        foreach ($this->exporterPool->all() as $exporter) {
            foreach ($exporter->export($customerId, $storeId) as $name => $dataset) {
                if (preg_match('/^[a-z0-9][a-z0-9-]{0,63}$/', $name) !== 1) {
                    throw new DomainException(sprintf(
                        'GDPR exporter "%s" returned an unsafe dataset name.',
                        $exporter->getCode()
                    ));
                }
                if (isset($datasets[$name])) {
                    throw new DomainException(sprintf('Duplicate GDPR export dataset "%s".', $name));
                }
                if ($dataset['columns'] === []) {
                    throw new DomainException(sprintf('GDPR export dataset "%s" has no columns.', $name));
                }
                $datasets[$name] = $dataset;
            }
        }
        return $datasets;
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
