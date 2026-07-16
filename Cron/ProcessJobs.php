<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Cron;

use Kkkonrad\Gdpr\Application\JobRunner;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Kkkonrad\Gdpr\Infrastructure\Persistence\JobQueue;
use Throwable;

class ProcessJobs
{
    private const XML_PATH_BATCH_SIZE = 'kkkonrad_gdpr/data_rights/batch_size';

    public function __construct(
        private readonly JobRunner $jobRunner,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ResourceConnection $resourceConnection,
        private readonly JobQueue $jobQueue
    ) {
    }

    public function execute(): void
    {
        $limit = max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_BATCH_SIZE));
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_job_run');
        $connection = $this->resourceConnection->getConnection();
        $connection->insert($table, [
            'job_code' => 'process_jobs',
            'status' => 'running',
            'found_count' => $this->jobQueue->countQueued(),
        ]);
        $runId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
        try {
            $result = $this->jobRunner->run($limit);
            $connection->update($table, [
                'status' => $result['failed'] > 0 ? 'completed_with_errors' : 'completed',
                'processed_count' => $result['processed'],
                'failed_count' => $result['failed'],
                'finished_at' => gmdate('Y-m-d H:i:s'),
            ], ['run_id = ?' => $runId]);
        } catch (Throwable $exception) {
            $connection->update($table, [
                'status' => 'failed',
                'failed_count' => 1,
                'finished_at' => gmdate('Y-m-d H:i:s'),
            ], ['run_id = ?' => $runId]);
            throw $exception;
        }
    }
}
