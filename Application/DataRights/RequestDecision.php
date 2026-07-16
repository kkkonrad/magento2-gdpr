<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights;

use DomainException;
use Kkkonrad\Gdpr\Api\JobSchedulerInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\Anonymization\AnonymizationProcessor;
use Kkkonrad\Gdpr\Application\DataRights\Erasure\ErasureProcessor;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;

class RequestDecision
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly EligibilityPolicy $eligibilityPolicy,
        private readonly RequestManagementInterface $requestManagement,
        private readonly JobSchedulerInterface $jobScheduler,
        private readonly FeatureManagerInterface $featureManager
    ) {
    }

    public function approve(
        int $requestId,
        int $adminId,
        string $publicReason,
        string $adminReason
    ): void {
        if (trim($publicReason) === '' || trim($adminReason) === '') {
            throw new DomainException('Both customer-safe and administrative reasons are required.');
        }
        $request = $this->getRequest($requestId);
        if ((string)$request['status'] !== RequestStatus::PENDING_APPROVAL) {
            throw new DomainException('Only a request pending approval can be approved.');
        }
        $customerId = (int)$request['customer_id'];
        $type = (string)$request['type'];
        $storeId = (int)$request['store_id'];
        $eligibility = $this->eligibilityPolicy->evaluate($customerId, $type, $storeId);
        if (!$eligibility['eligible']) {
            throw new DomainException($eligibility['message']);
        }
        [$jobType, $feature] = match ($type) {
            RequestType::ANONYMIZE => [AnonymizationProcessor::TYPE, FeatureCode::ANONYMIZATION_REQUEST],
            RequestType::ERASE => [ErasureProcessor::TYPE, FeatureCode::ERASURE_REQUEST],
            default => throw new DomainException('This request type does not require administrative approval.'),
        };
        if (!$this->featureManager->isEnabled($feature, $storeId)) {
            throw new DomainException('The corresponding GDPR feature is disabled for this store view.');
        }
        $this->requestManagement->transition(
            $requestId,
            RequestStatus::QUEUED,
            'admin',
            $adminId,
            trim($publicReason),
            trim($adminReason),
            ['decision' => 'approved']
        );
        $this->jobScheduler->schedule(
            $jobType,
            $feature,
            $storeId,
            ['customer_id' => $customerId],
            $requestId,
            $jobType . '-request-' . $requestId
        );
    }

    public function reject(
        int $requestId,
        int $adminId,
        string $publicReason,
        string $adminReason
    ): void {
        if (trim($publicReason) === '' || trim($adminReason) === '') {
            throw new DomainException('Both customer-safe and administrative reasons are required.');
        }
        $request = $this->getRequest($requestId);
        if ((string)$request['status'] !== RequestStatus::PENDING_APPROVAL) {
            throw new DomainException('Only a request pending approval can be rejected.');
        }
        $this->requestManagement->transition(
            $requestId,
            RequestStatus::REJECTED,
            'admin',
            $adminId,
            trim($publicReason),
            trim($adminReason),
            ['decision' => 'rejected']
        );
    }

    public function retry(int $requestId, int $adminId, string $adminReason): void
    {
        if (trim($adminReason) === '') {
            throw new DomainException('An administrative retry reason is required.');
        }
        $request = $this->getRequest($requestId);
        if (!in_array((string)$request['status'], [RequestStatus::FAILED, RequestStatus::PARTIALLY_COMPLETED], true)) {
            throw new DomainException('Only a failed or partially completed request can be retried.');
        }
        $jobTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        $featureCode = $this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()->select()
                ->from($jobTable, ['feature_code'])
                ->where('request_id = ?', $requestId)
                ->order('job_id DESC')
                ->limit(1)
        );
        if (!is_string($featureCode)
            || !$this->featureManager->isEnabled($featureCode, (int)$request['store_id'])
        ) {
            throw new DomainException('The corresponding GDPR feature is disabled for this store view.');
        }
        $this->requestManagement->transition(
            $requestId,
            RequestStatus::QUEUED,
            'admin',
            $adminId,
            (string)__('The request was queued for another processing attempt.'),
            trim($adminReason),
            ['decision' => 'retry']
        );
        $this->resourceConnection->getConnection()->update($jobTable, [
            'status' => 'queued',
            'available_at' => gmdate('Y-m-d H:i:s'),
            'locked_at' => null,
            'locked_by' => null,
            'error_code' => null,
            'error_message' => null,
            'finished_at' => null,
        ], [
            'request_id = ?' => $requestId,
            'status = ?' => 'failed',
        ]);
    }

    /** @return array<string, mixed> */
    private function getRequest(int $requestId): array
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $row = $this->resourceConnection->getConnection()->fetchRow(
            $this->resourceConnection->getConnection()->select()
                ->from($table)
                ->where('request_id = ?', $requestId)
        );
        if ($row === false) {
            throw NoSuchEntityException::singleField('request_id', $requestId);
        }

        return $row;
    }
}
