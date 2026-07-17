<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Adminhtml\Health;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;

class Dashboard extends Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Template\Context $context,
        private readonly ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<string, int|string|null> */
    public function getSummary(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $jobTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        $runTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_job_run');
        $counts = [];
        foreach (['queued', 'claimed', 'processing', 'failed', 'partially_completed'] as $status) {
            $counts[$status] = (int)$connection->fetchOne(
                $connection->select()->from($jobTable, ['COUNT(*)'])->where('status = ?', $status)
            );
        }
        $counts['oldest_queued_at'] = $connection->fetchOne(
            $connection->select()->from($jobTable, ['MIN(created_at)'])->where('status = ?', 'queued')
        ) ?: null;
        $counts['last_success_at'] = $connection->fetchOne(
            $connection->select()->from($runTable, ['MAX(finished_at)'])->where('status = ?', 'completed')
        ) ?: null;
        return $counts;
    }

    /** @return array<int, array<string, mixed>> */
    public function getRuns(): array
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_job_run');
        return $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()
                ->from($table)
                ->order('run_id DESC')
                ->limit(100)
        );
    }
}
