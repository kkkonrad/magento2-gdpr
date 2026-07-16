<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\DataRights;

interface LegalHoldProviderInterface
{
    public function getCode(): string;

    /**
     * Return a customer-safe blocking reason or null when no hold exists.
     */
    public function getBlockReason(int $customerId, string $operation, int $storeId): ?string;
}
