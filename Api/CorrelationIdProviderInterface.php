<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api;

interface CorrelationIdProviderInterface
{
    public function get(): string;
}
