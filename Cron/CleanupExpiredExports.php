<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Cron;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;

class CleanupExpiredExports
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DirectoryList $directoryList,
        private readonly RequestManagementInterface $requestManagement
    ) {
    }

    public function execute(): void
    {
        $exportTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_export');
        $requestTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from(['export' => $exportTable])
                ->joinInner(['request' => $requestTable], 'request.request_id = export.request_id', [
                    'request_status' => 'status',
                ])
                ->where('export.expires_at <= UTC_TIMESTAMP()')
                ->limit(250)
        );
        $varPath = realpath($this->directoryList->getPath(DirectoryList::VAR_DIR));
        foreach ($rows as $row) {
            $requestStatus = (string)$row['request_status'];
            if (!in_array($requestStatus, [RequestStatus::COMPLETED, RequestStatus::EXPIRED], true)) {
                continue;
            }
            $relativePath = ltrim((string)$row['storage_path'], '/');
            if (preg_match('#^kkkonrad/gdpr/exports/[a-f0-9-]+\.zip$#', $relativePath) !== 1) {
                continue;
            }
            if ($requestStatus === RequestStatus::COMPLETED) {
                $this->requestManagement->transition(
                    (int)$row['request_id'],
                    RequestStatus::EXPIRED,
                    'system',
                    null,
                    (string)__('The generated export has expired.'),
                    null,
                    ['export_id' => (int)$row['export_id']]
                );
            }
            $path = realpath($this->directoryList->getPath(DirectoryList::VAR_DIR) . '/' . $relativePath);
            if ($varPath !== false && $path !== false && str_starts_with($path, $varPath . DIRECTORY_SEPARATOR)) {
                if (!unlink($path)) {
                    continue;
                }
            }
            $connection->delete($exportTable, ['export_id = ?' => (int)$row['export_id']]);
        }
    }
}
