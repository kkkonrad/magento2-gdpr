<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights;

use DomainException;
use Kkkonrad\Gdpr\Api\DataRights\ReauthenticationInterface;
use Magento\Customer\Model\AuthenticationInterface;

class PasswordReauthentication implements ReauthenticationInterface
{
    public function __construct(
        private readonly AuthenticationInterface $authentication
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
        // Validate the credential without dispatching Magento's full login event sequence.
        // AccountManagement::authenticate() is intended for a new login and can mutate the
        // active persistent/session state when it is called from an already authenticated request.
        $this->authentication->authenticate($customerId, $credential);
    }
}
