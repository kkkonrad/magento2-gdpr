<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights;

use DomainException;
use Kkkonrad\Gdpr\Api\DataRights\ReauthenticationInterface;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;

class PasswordReauthentication implements ReauthenticationInterface
{
    public function __construct(
        private readonly AccountManagementInterface $accountManagement,
        private readonly CustomerRepositoryInterface $customerRepository
    ) {
    }

    public function requiresPassword(int $customerId, int $storeId): bool
    {
        unset($customerId, $storeId);
        return true;
    }

    public function reauthenticate(int $customerId, int $storeId, ?string $credential): void
    {
        unset($storeId);
        if ($credential === null || $credential === '') {
            throw new DomainException((string)__('Current password is required.'));
        }
        $customer = $this->customerRepository->getById($customerId);
        $this->accountManagement->authenticate((string)$customer->getEmail(), $credential);
    }
}
