<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Cron;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;

class CleanupExpiredExports
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly DirectoryList $directoryList
    ) {
    }

    public function execute(): void
    {
        $exportTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_export');
        $requestTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $connection = $this->resourceConnection->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()->from($exportTable)->where('expires_at <= UTC_TIMESTAMP()')->limit(250)
        );
        $varPath = realpath($this->directoryList->getPath(DirectoryList::VAR_DIR));
        foreach ($rows as $row) {
            $path = realpath($this->directoryList->getPath(DirectoryList::VAR_DIR) . '/' . ltrim(
                (string)$row['storage_path'],
                '/'
            ));
            if ($varPath !== false && $path !== false && str_starts_with($path, $varPath . DIRECTORY_SEPARATOR)) {
                unlink($path);
            }
            $connection->delete($exportTable, ['export_id = ?' => (int)$row['export_id']]);
            $connection->update($requestTable, [
                'public_reason' => (string)__('The generated export has expired.'),
            ], ['request_id = ?' => (int)$row['request_id']]);
        }
    }
}
