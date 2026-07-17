<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Notification;

use DomainException;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;

class RequestNotification
{
    private const EVENTS = ['submitted', 'approved', 'rejected', 'completed', 'failed', 'retention_warning'];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly NotificationOutbox $notificationOutbox
    ) {
    }

    /** @param array<string, mixed> $variables */
    public function prepare(int $requestId, string $event, bool $hold = false, array $variables = []): int
    {
        if (!in_array($event, self::EVENTS, true)) {
            throw new DomainException(sprintf('Unsupported request notification event "%s".', $event));
        }
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $request = $this->resourceConnection->getConnection()->fetchRow(
            $this->resourceConnection->getConnection()->select()
                ->from($table, ['customer_id', 'store_id', 'public_id', 'type'])
                ->where('request_id = ?', $requestId)
        );
        if ($request === false || $request['customer_id'] === null) {
            throw NoSuchEntityException::singleField('request_id', $requestId);
        }
        $customer = $this->customerRepository->getById((int)$request['customer_id']);
        $storeId = (int)$request['store_id'];
        $template = (string)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/email/request_' . $event . '_template',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        if ($template === '') {
            $template = 'kkkonrad_gdpr_request_' . $event;
        }
        $id = $this->notificationOutbox->prepare(
            $requestId,
            'request.' . $event,
            (string)$customer->getEmail(),
            trim((string)$customer->getFirstname() . ' ' . (string)$customer->getLastname()),
            $template,
            $storeId,
            array_merge([
                'request_id' => (string)$request['public_id'],
                'request_type' => (string)$request['type'],
            ], $variables),
            $hold
        );
        if (!$hold) {
            $this->notificationOutbox->sendForRequest($requestId, 'request.' . $event);
        }
        return $id;
    }

    public function release(int $requestId, string $event): bool
    {
        if (!in_array($event, self::EVENTS, true)) {
            throw new DomainException(sprintf('Unsupported request notification event "%s".', $event));
        }
        return $this->notificationOutbox->sendForRequest($requestId, 'request.' . $event);
    }
}
