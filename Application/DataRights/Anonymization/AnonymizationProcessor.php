<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Anonymization;

use DomainException;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\EligibilityPolicy;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorInterface;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class AnonymizationProcessor implements JobProcessorInterface
{
    public const TYPE = 'customer.anonymize';

    public function __construct(
        private readonly EligibilityPolicy $eligibilityPolicy,
        private readonly CustomerDataAnonymizer $customerDataAnonymizer,
        private readonly RequestManagementInterface $requestManagement,
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function getType(): string
    {
        return self::TYPE;
    }

    public function process(JobContext $context): void
    {
        $customerId = (int)($context->payload['customer_id'] ?? 0);
        if ($customerId <= 0 || $context->requestId === null) {
            throw new DomainException('Anonymization job has no customer or request reference.');
        }
        $this->requestManagement->transition($context->requestId, RequestStatus::PROCESSING, 'system');
        try {
            $eligibility = $this->eligibilityPolicy->evaluate($customerId, RequestType::ANONYMIZE, $context->storeId);
            if (!$eligibility['eligible']) {
                throw new DomainException($eligibility['message']);
            }
            $counts = $this->customerDataAnonymizer->anonymize($customerId, $context->publicId);
            $this->recordResult($context->jobId, 'customer_data', $counts);
            $this->requestManagement->transition(
                $context->requestId,
                RequestStatus::COMPLETED,
                'system',
                null,
                (string)__('Your personal data has been anonymized.'),
                null,
                ['processed_entities' => array_sum($counts)]
            );
        } catch (Throwable $exception) {
            $this->requestManagement->transition(
                $context->requestId,
                RequestStatus::FAILED,
                'system',
                null,
                (string)__('The anonymization could not be completed and requires review.'),
                null,
                ['error_code' => 'anonymization_failed']
            );
            throw $exception;
        }
    }

    /** @param array<string, int> $counts */
    private function recordResult(int $jobId, string $processorCode, array $counts): void
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_processor_result');
        $this->resourceConnection->getConnection()->insertOnDuplicate($table, [
            'job_id' => $jobId,
            'processor_code' => $processorCode,
            'status' => 'completed',
            'processed_count' => array_sum($counts),
            'skipped_count' => 0,
        ], ['status', 'processed_count', 'skipped_count']);
    }
}
