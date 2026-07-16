<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\Geo;

interface RegionResolverInterface
{
    /** @return array{region:string|null, source:string, confidence:string} */
    public function resolve(): array;
}
