<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights;

use DomainException;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\JobSchedulerInterface;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\Export\ExportGenerationProcessor;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;

class RequestSubmission
{
    public function __construct(
        private readonly FeatureManagerInterface $featureManager,
        private readonly RequestManagementInterface $requestManagement,
        private readonly JobSchedulerInterface $jobScheduler,
        private readonly EligibilityPolicy $eligibilityPolicy
    ) {
    }

    public function submit(int $customerId, string $type, int $storeId): int
    {
        RequestType::assertValid($type);
        $feature = match ($type) {
            RequestType::EXPORT => FeatureCode::EXPORT_REQUEST,
            RequestType::ANONYMIZE => FeatureCode::ANONYMIZATION_REQUEST,
            RequestType::ERASE => FeatureCode::ERASURE_REQUEST,
            default => throw new DomainException('Unsupported privacy request type.'),
        };
        if (!$this->featureManager->isEnabled($feature, $storeId)) {
            throw new DomainException('This privacy request type is disabled.');
        }
        $requestId = $this->requestManagement->submit($customerId, $type, $storeId);
        $this->requestManagement->transition($requestId, RequestStatus::VALIDATION, 'system');
        $eligibility = $this->eligibilityPolicy->evaluate($customerId, $type, $storeId);
        if (!$eligibility['eligible']) {
            $this->requestManagement->transition(
                $requestId,
                RequestStatus::BLOCKED,
                'system',
                null,
                $eligibility['message'],
                null,
                ['reason_code' => $eligibility['code']]
            );
            return $requestId;
        }
        if ($type === RequestType::EXPORT) {
            $this->requestManagement->transition($requestId, RequestStatus::QUEUED, 'system');
            $this->jobScheduler->schedule(
                ExportGenerationProcessor::TYPE,
                FeatureCode::EXPORT_REQUEST,
                $storeId,
                ['customer_id' => $customerId],
                $requestId,
                'export-request-' . $requestId
            );
        } else {
            $this->requestManagement->transition(
                $requestId,
                RequestStatus::PENDING_APPROVAL,
                'system',
                null,
                (string)__('Your request is waiting for administrative review.')
            );
        }

        return $requestId;
    }
}
