<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\DataRights;

interface ReauthenticationInterface
{
    public function requiresPassword(int $customerId, int $storeId): bool;

    /**
     * A passwordless implementation may ignore $credential and validate a provider/session challenge.
     */
    public function reauthenticate(int $customerId, int $storeId, ?string $credential): void;
}
