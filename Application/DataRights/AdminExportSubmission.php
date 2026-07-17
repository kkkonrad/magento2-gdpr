<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights;

use DomainException;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\JobSchedulerInterface;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\Export\ExportGenerationProcessor;
use Kkkonrad\Gdpr\Application\Notification\RequestNotification;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;
use Throwable;

class AdminExportSubmission
{
    public function __construct(
        private readonly FeatureManagerInterface $featureManager,
        private readonly RequestManagementInterface $requestManagement,
        private readonly JobSchedulerInterface $jobScheduler,
        private readonly EligibilityPolicy $eligibilityPolicy,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly RequestNotification $requestNotification,
        private readonly LoggerInterface $logger,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function submit(int $customerId, int $adminId, string $adminReason): int
    {
        $adminReason = trim($adminReason);
        if ($customerId <= 0 || $adminId <= 0) {
            throw new DomainException((string)__('A valid customer and administrator are required.'));
        }
        if ($adminReason === '') {
            throw new DomainException((string)__('An administrative justification is required.'));
        }
        if (mb_strlen($adminReason) > 2000) {
            throw new DomainException((string)__('The administrative justification is too long.'));
        }

        $customer = $this->customerRepository->getById($customerId);
        $storeId = (int)$customer->getStoreId();
        if (!$this->featureManager->isEnabled(FeatureCode::EXPORT_REQUEST, $storeId)) {
            throw new DomainException((string)__('Customer data exports are disabled for this store view.'));
        }
        $eligibility = $this->eligibilityPolicy->evaluate($customerId, RequestType::EXPORT, $storeId);
        if (!$eligibility['eligible']) {
            throw new DomainException($eligibility['message']);
        }

        $connection = $this->resourceConnection->getConnection();
        $connection->beginTransaction();
        try {
            $requestId = $this->requestManagement->submit(
                $customerId,
                RequestType::EXPORT,
                $storeId,
                null,
                'admin',
                $adminId
            );
            $this->requestManagement->transition(
                $requestId,
                RequestStatus::VALIDATION,
                'admin',
                $adminId,
                (string)__('An administrator initiated this data export.'),
                $adminReason,
                ['source' => 'admin_on_behalf']
            );
            $this->requestManagement->transition($requestId, RequestStatus::QUEUED, 'admin', $adminId);
            $this->jobScheduler->schedule(
                ExportGenerationProcessor::TYPE,
                FeatureCode::EXPORT_REQUEST,
                $storeId,
                ['customer_id' => $customerId],
                $requestId,
                'admin-export-request-' . $requestId
            );
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }

        try {
            $this->requestNotification->prepare($requestId, 'submitted');
        } catch (Throwable) {
            $this->logger->warning('An administrator created a GDPR export but its notification could not be queued.', [
                'request_id' => $requestId,
                'error_code' => 'notification_prepare_failed',
            ]);
        }

        return $requestId;
    }
}
