<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights;

use DomainException;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\ConfigProviderInterface;
use Kkkonrad\Gdpr\Api\JobSchedulerInterface;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\Export\ExportGenerationProcessor;
use Kkkonrad\Gdpr\Application\Notification\RequestNotification;
use Kkkonrad\Gdpr\Application\Notification\AdminNotification;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Psr\Log\LoggerInterface;
use Magento\Framework\App\CacheInterface;
use Throwable;
use Magento\Framework\App\ResourceConnection;

class RequestSubmission
{
    public function __construct(
        private readonly FeatureManagerInterface $featureManager,
        private readonly RequestManagementInterface $requestManagement,
        private readonly JobSchedulerInterface $jobScheduler,
        private readonly EligibilityPolicy $eligibilityPolicy,
        private readonly RequestNotification $requestNotification,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache,
        private readonly ConfigProviderInterface $configProvider,
        private readonly AdminNotification $adminNotification,
        private readonly ResourceConnection $resourceConnection
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
            throw new DomainException((string)__('This privacy request type is disabled.'));
        }
        $this->assertRateLimit($customerId, $type, $storeId);
        $connection = $this->resourceConnection->getConnection();
        $isEligible = false;
        $connection->beginTransaction();
        try {
            $requestId = $this->requestManagement->submit($customerId, $type, $storeId);
            $this->requestManagement->transition($requestId, RequestStatus::VALIDATION, 'system');
            $eligibility = $this->eligibilityPolicy->evaluate($customerId, $type, $storeId);
            $isEligible = $eligibility['eligible'];
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
            } elseif ($type === RequestType::EXPORT) {
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
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        $this->notifySubmitted($requestId);
        if ($type === RequestType::ERASE && $isEligible) {
            try {
                $this->adminNotification->erasureRequested($requestId, $storeId);
            } catch (Throwable) {
                $this->logger->warning('A new GDPR erasure request could not be announced to administrators.', [
                    'request_id' => $requestId,
                    'error_code' => 'admin_notification_failed',
                ]);
            }
        }

        return $requestId;
    }

    private function assertRateLimit(int $customerId, string $type, int $storeId): void
    {
        $limit = $this->configProvider->getPositiveInt(
            'kkkonrad_gdpr/data_rights/request_rate_limit_per_hour',
            $storeId,
            5,
            20
        );
        $key = 'kkkonrad_gdpr_request_rate_' . hash('sha256', implode('|', [
            (string)$customerId,
            $type,
            (string)$storeId,
        ]));
        $count = (int)$this->cache->load($key);
        if ($count >= $limit) {
            throw new DomainException(
                (string)__('Too many privacy requests were submitted. Please try again later.')
            );
        }
        $this->cache->save((string)($count + 1), $key, [], 3600);
    }

    private function notifySubmitted(int $requestId): void
    {
        try {
            $this->requestNotification->prepare($requestId, 'submitted');
        } catch (Throwable) {
            $this->logger->warning('A GDPR request was submitted but its acknowledgement email was queued unsuccessfully.', [
                'request_id' => $requestId,
                'error_code' => 'notification_prepare_failed',
            ]);
        }
    }
}
