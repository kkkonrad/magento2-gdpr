<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\Cookie;

interface CookiePolicyVersionProviderInterface
{
    /**
     * @return array{policy_version_id:int, public_id:string, version:int, configuration_hash:string}
     */
    public function getOrPublishCurrent(int $storeId): array;
}
