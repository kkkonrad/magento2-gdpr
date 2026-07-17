<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Infrastructure\Persistence;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Api\ClockInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\JobSchedulerInterface;
use Kkkonrad\Gdpr\Api\RandomIdGeneratorInterface;
use Kkkonrad\Gdpr\Domain\Shared\Audit\SensitiveDataRedactor;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobStatus;
use Magento\Framework\App\ResourceConnection;
use Zend_Db_Expr;
use DateTimeInterface;

class JobQueue implements JobSchedulerInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly FeatureManagerInterface $featureManager,
        private readonly SensitiveDataRedactor $redactor,
        private readonly ClockInterface $clock,
        private readonly RandomIdGeneratorInterface $randomIdGenerator
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $configSnapshot
     */
    public function schedule(
        string $type,
        string $featureCode,
        int $storeId,
        array $payload = [],
        ?int $requestId = null,
        ?string $idempotencyKey = null,
        array $configSnapshot = [],
        ?DateTimeInterface $availableAt = null
    ): int {
        if ($type === '') {
            throw new InvalidArgumentException('A GDPR job type is required.');
        }
        if (!in_array($featureCode, FeatureCode::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Unknown GDPR feature code "%s".', $featureCode));
        }
        if (!$this->featureManager->isEnabled($featureCode, $storeId)) {
            throw new InvalidArgumentException(sprintf(
                'GDPR feature "%s" is disabled for store %d.',
                $featureCode,
                $storeId
            ));
        }

        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        $connection = $this->resourceConnection->getConnection();
        $idempotencyKey ??= hash('sha256', implode('|', [
            $type,
            $featureCode,
            (string)$storeId,
            (string)($requestId ?? 0),
            json_encode($this->redactor->redact($payload), JSON_THROW_ON_ERROR),
        ]));

        $data = [
            'public_id' => $this->randomIdGenerator->uuid(),
            'idempotency_key' => $idempotencyKey,
            'type' => $type,
            'feature_code' => $featureCode,
            'status' => JobStatus::QUEUED,
            'request_id' => $requestId,
            'store_id' => $storeId,
            'payload_json' => $payload === []
                ? null
                : json_encode($this->redactor->redact($payload), JSON_THROW_ON_ERROR),
            'config_snapshot_json' => $configSnapshot === []
                ? null
                : json_encode($this->redactor->redact($configSnapshot), JSON_THROW_ON_ERROR),
        ];
        if ($availableAt !== null) {
            $data['available_at'] = $availableAt->format('Y-m-d H:i:s');
        }
        $connection->insertOnDuplicate($table, $data, ['idempotency_key']);

        return (int)$connection->fetchOne(
            $connection->select()->from($table, ['job_id'])->where('idempotency_key = ?', $idempotencyKey)
        );
    }

    /** @return array<string, mixed>|null */
    /**
     * @param int[]|null $storeIds
     * @return array<string, mixed>|null
     */
    public function claimNext(string $workerId, ?string $type = null, ?array $storeIds = null): ?array
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($table)
            ->where('status = ?', JobStatus::QUEUED)
            ->where('available_at <= UTC_TIMESTAMP()');
        if ($type !== null) {
            $select->where('type = ?', $type);
        }
        if ($storeIds !== null) {
            if ($storeIds === []) {
                return null;
            }
            $select->where('store_id IN (?)', array_values(array_unique(array_map('intval', $storeIds))));
        }
        $candidateRows = $connection->fetchAll($select->order('job_id ASC')->limit(25));

        foreach ($candidateRows as $row) {
            if (!$this->featureManager->isEnabled((string)$row['feature_code'], (int)$row['store_id'])) {
                continue;
            }
            $affected = $connection->update($table, [
                'status' => JobStatus::CLAIMED,
                'locked_at' => $this->clock->now()->format('Y-m-d H:i:s'),
                'locked_by' => $workerId,
                'attempt_count' => new Zend_Db_Expr('attempt_count + 1'),
            ], [
                'job_id = ?' => (int)$row['job_id'],
                'status = ?' => JobStatus::QUEUED,
            ]);
            if ($affected === 1) {
                return $connection->fetchRow(
                    $connection->select()->from($table)->where('job_id = ?', (int)$row['job_id'])
                ) ?: null;
            }
        }

        return null;
    }

    public function markProcessing(int $jobId): void
    {
        $this->updateStatus($jobId, JobStatus::PROCESSING, [
            'error_code' => null,
            'error_message' => null,
        ]);
    }

    public function complete(int $jobId): void
    {
        $this->updateStatus($jobId, JobStatus::COMPLETED, [
            'locked_at' => null,
            'locked_by' => null,
            'finished_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function partiallyComplete(int $jobId, string $errorCode, string $safeMessage): void
    {
        $this->updateStatus($jobId, JobStatus::PARTIALLY_COMPLETED, [
            'locked_at' => null,
            'locked_by' => null,
            'error_code' => mb_substr($errorCode, 0, 64),
            'error_message' => mb_substr($safeMessage, 0, 2000),
            'finished_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function fail(int $jobId, string $errorCode, string $safeMessage): void
    {
        $this->updateStatus($jobId, JobStatus::FAILED, [
            'locked_at' => null,
            'locked_by' => null,
            'error_code' => mb_substr($errorCode, 0, 64),
            'error_message' => mb_substr($safeMessage, 0, 2000),
            'finished_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);
    }

    public function retryLater(int $jobId, int $delaySeconds, string $errorCode, string $safeMessage): void
    {
        $this->updateStatus($jobId, JobStatus::QUEUED, [
            'locked_at' => null,
            'locked_by' => null,
            'available_at' => $this->clock->now()
                ->modify('+' . max(1, min(3600, $delaySeconds)) . ' seconds')
                ->format('Y-m-d H:i:s'),
            'error_code' => mb_substr($errorCode, 0, 64),
            'error_message' => mb_substr($safeMessage, 0, 2000),
            'finished_at' => null,
        ]);
    }

    public function releaseStaleClaims(int $ageSeconds = 900): int
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        $threshold = $this->clock->now()->modify('-' . max(60, $ageSeconds) . ' seconds')->format('Y-m-d H:i:s');
        return $this->resourceConnection->getConnection()->update($table, [
            'status' => JobStatus::QUEUED,
            'locked_at' => null,
            'locked_by' => null,
            'available_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ], [
            'status = ?' => JobStatus::CLAIMED,
            'locked_at < ?' => $threshold,
        ]);
    }

    /** @param int[]|null $storeIds */
    public function countQueued(?string $type = null, ?array $storeIds = null): int
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()->from($table, ['COUNT(*)'])->where('status = ?', JobStatus::QUEUED);
        if ($type !== null) {
            $select->where('type = ?', $type);
        }
        if ($storeIds !== null) {
            if ($storeIds === []) {
                return 0;
            }
            $select->where('store_id IN (?)', array_values(array_unique(array_map('intval', $storeIds))));
        }
        return (int)$connection->fetchOne($select);
    }

    /** @param array<string, mixed> $additionalData */
    private function updateStatus(int $jobId, string $status, array $additionalData): void
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        $this->resourceConnection->getConnection()->update(
            $table,
            array_merge(['status' => $status], $additionalData),
            ['job_id = ?' => $jobId]
        );
    }
}
