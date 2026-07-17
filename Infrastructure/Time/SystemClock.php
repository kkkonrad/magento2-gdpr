<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Infrastructure\Time;

use DateTimeImmutable;
use DateTimeZone;
use Kkkonrad\Gdpr\Api\ClockInterface;

class SystemClock implements ClockInterface
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function timestamp(): int
    {
        return $this->now()->getTimestamp();
    }
}
