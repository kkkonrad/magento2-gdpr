<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Consent;

use DomainException;

class ConsentDecision
{
    public const ACCEPTED = 'accepted';
    public const DECLINED = 'declined';

    public const ALL = [self::ACCEPTED, self::DECLINED];

    public static function assertValid(string $decision): void
    {
        if (!in_array($decision, self::ALL, true)) {
            throw new DomainException(sprintf('Unsupported consent decision "%s".', $decision));
        }
    }

    private function __construct()
    {
    }
}
