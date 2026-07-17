<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application;

use DomainException;
use Kkkonrad\Gdpr\Api\ClockInterface;
use Kkkonrad\Gdpr\Api\CorrelationIdProviderInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Application\Audit\AuditWriter;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobStatus;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Throwable;

class JobRetry
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly FeatureManagerInterface $featureManager,
        private readonly ClockInterface $clock,
        private readonly CorrelationIdProviderInterface $correlationIdProvider,
        private readonly AuditWriter $auditWriter
    ) {
    }

    public function retry(int $jobId, string $reasonCode): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9_.-]{2,63}$/', $reasonCode) !== 1) {
            throw new DomainException('Retry reason code must use lowercase letters, digits, dots, dashes or underscores.');
        }
        $connection = $this->resourceConnection->getConnection();
        $jobTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        $requestTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $eventTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_request_event');
        $connection->beginTransaction();
        try {
            $job = $connection->fetchRow(
                $connection->select()->from($jobTable)->where('job_id = ?', $jobId)->forUpdate(true)
            );
            if ($job === false) {
                throw NoSuchEntityException::singleField('job_id', $jobId);
            }
            if (!in_array((string)$job['status'], [JobStatus::FAILED, JobStatus::PARTIALLY_COMPLETED], true)) {
                throw new DomainException('Only a failed or partially completed GDPR job can be retried.');
            }
            if (!$this->featureManager->isEnabled((string)$job['feature_code'], (int)$job['store_id'])) {
                throw new DomainException('The feature owning this GDPR job is disabled for its store view.');
            }
            if ($job['request_id'] !== null) {
                $request = $connection->fetchRow(
                    $connection->select()
                        ->from($requestTable, ['status'])
                        ->where('request_id = ?', (int)$job['request_id'])
                        ->forUpdate(true)
                );
                if ($request !== false) {
                    $status = (string)$request['status'];
                    if (!in_array($status, [RequestStatus::FAILED, RequestStatus::PARTIALLY_COMPLETED], true)) {
                        throw new DomainException('The related GDPR request is not retryable.');
                    }
                    $connection->update($requestTable, [
                        'status' => RequestStatus::QUEUED,
                        'completed_at' => null,
                        'public_reason' => (string)__('The request was queued for another processing attempt.'),
                    ], ['request_id = ?' => (int)$job['request_id']]);
                    $connection->insert($eventTable, [
                        'request_id' => (int)$job['request_id'],
                        'event_type' => 'request.retry_queued',
                        'status_from' => $status,
                        'status_to' => RequestStatus::QUEUED,
                        'actor_type' => 'system',
                        'correlation_id' => $this->correlationIdProvider->get(),
                        'metadata_json' => json_encode(['reason_code' => $reasonCode], JSON_THROW_ON_ERROR),
                    ]);
                }
            }
            $connection->update($jobTable, [
                'status' => JobStatus::QUEUED,
                'available_at' => $this->clock->now()->format('Y-m-d H:i:s'),
                'locked_at' => null,
                'locked_by' => null,
                'error_code' => null,
                'error_message' => null,
                'finished_at' => null,
            ], ['job_id = ?' => $jobId]);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
        $this->auditWriter->write(
            'job.retry_queued',
            'gdpr_job',
            (string)$jobId,
            'system',
            null,
            (int)$job['store_id'],
            $this->correlationIdProvider->get(),
            ['reason_code' => $reasonCode]
        );
    }
}
