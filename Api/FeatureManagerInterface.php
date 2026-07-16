<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api;

interface FeatureManagerInterface
{
    public function isEnabled(string $featureCode, int|string|null $scopeCode = null): bool;
}
