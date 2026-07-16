<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Erasure;

use DomainException;
use Kkkonrad\Gdpr\Api\RequestManagementInterface;
use Kkkonrad\Gdpr\Application\DataRights\Anonymization\CustomerDataAnonymizer;
use Kkkonrad\Gdpr\Application\DataRights\EligibilityPolicy;
use Kkkonrad\Gdpr\Application\Notification\NotificationSender;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestStatus;
use Kkkonrad\Gdpr\Domain\DataRights\Request\RequestType;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Throwable;
use Psr\Log\LoggerInterface;

class ErasureProcessor implements JobProcessorInterface
{
    public const TYPE = 'customer.erase';

    public function __construct(
        private readonly EligibilityPolicy $eligibilityPolicy,
        private readonly CustomerDataAnonymizer $customerDataAnonymizer,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly SecureAreaExecutor $secureAreaExecutor,
        private readonly RequestManagementInterface $requestManagement,
        private readonly ResourceConnection $resourceConnection,
        private readonly NotificationSender $notificationSender,
        private readonly LoggerInterface $logger
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
            throw new DomainException('Erasure job has no customer or request reference.');
        }
        $this->requestManagement->transition($context->requestId, RequestStatus::PROCESSING, 'system');
        try {
            $eligibility = $this->eligibilityPolicy->evaluate($customerId, RequestType::ERASE, $context->storeId);
            if (!$eligibility['eligible']) {
                throw new DomainException($eligibility['message']);
            }
            $customer = $this->customerRepository->getById($customerId);
            $transport = $this->notificationSender->prepare(
                (string)$customer->getEmail(),
                trim((string)$customer->getFirstname() . ' ' . (string)$customer->getLastname()),
                'kkkonrad_gdpr_erasure_completed',
                $context->storeId,
                ['request_id' => $context->publicId]
            );
            $counts = $this->customerDataAnonymizer->anonymize($customerId, $context->publicId);
            $connection = $this->resourceConnection->getConnection();
            foreach (['vault_payment_token', 'wishlist', 'quote', 'newsletter_subscriber'] as $tableName) {
                $table = $this->resourceConnection->getTableName($tableName);
                $counts[$tableName] = $connection->delete($table, ['customer_id = ?' => $customerId]);
            }
            $this->secureAreaExecutor->execute(
                fn (): bool => $this->customerRepository->deleteById($customerId)
            );
            $counts['customer_deleted'] = 1;
            $this->requestManagement->transition(
                $context->requestId,
                RequestStatus::COMPLETED,
                'system',
                null,
                (string)__('Your account and erasable personal data have been deleted.'),
                null,
                ['processed_entities' => array_sum($counts)]
            );
            try {
                $transport->sendMessage();
            } catch (Throwable $notificationException) {
                $this->logger->warning('GDPR erasure completed but final notification failed.', [
                    'request_id' => $context->requestId,
                    'error_code' => 'notification_failed',
                ]);
            }
        } catch (Throwable $exception) {
            $this->requestManagement->transition(
                $context->requestId,
                RequestStatus::FAILED,
                'system',
                null,
                (string)__('The erasure could not be completed and requires review.'),
                null,
                ['error_code' => 'erasure_failed']
            );
            throw $exception;
        }
    }
}
