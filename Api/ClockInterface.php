<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api;

use DateTimeImmutable;

interface ClockInterface
{
    public function now(): DateTimeImmutable;

    public function timestamp(): int;
}
