<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\Response\Http\FileFactory;

class ExportCsv extends Action
{
    public const ADMIN_RESOURCE = 'Kkkonrad_Gdpr::requests_export';

    public function __construct(
        Context $context,
        private readonly ResourceConnection $resourceConnection,
        private readonly FileFactory $fileFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $columns = [
            'public_id', 'customer_id', 'type', 'status', 'store_id', 'public_reason',
            'due_at', 'created_at', 'updated_at', 'completed_at',
        ];
        $rows = $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()
                ->from($table, $columns)
                ->order('request_id DESC')
                ->limit(10000)
        );
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new \RuntimeException('Could not create the GDPR request export stream.');
        }
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, $columns, ',', '"', '\\');
        foreach ($rows as $row) {
            $values = [];
            foreach ($columns as $column) {
                $value = (string)($row[$column] ?? '');
                $values[] = preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
            }
            fputcsv($stream, $values, ',', '"', '\\');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);
        return $this->fileFactory->create(
            'gdpr-requests-' . gmdate('Ymd-His') . '.csv',
            ['type' => 'string', 'value' => $content === false ? '' : $content, 'rm' => true],
            DirectoryList::VAR_DIR,
            'text/csv; charset=UTF-8'
        );
    }
}
