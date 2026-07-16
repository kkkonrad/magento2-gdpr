<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\Consent;

interface ActiveConsentsProviderInterface
{
    /**
     * @return array<int, array<string, int|string|bool>>
     */
    public function getForLocation(string $location, int $storeId): array;
}
