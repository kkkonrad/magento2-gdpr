<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\Cookie;

interface CookieRegistryInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGroups(int $storeId): array;
}
