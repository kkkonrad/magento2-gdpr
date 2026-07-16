<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\DataRights\Request;

use InvalidArgumentException;

class RequestType
{
    public const EXPORT = 'export';
    public const ANONYMIZE = 'anonymize';
    public const ERASE = 'erase';

    public const ALL = [self::EXPORT, self::ANONYMIZE, self::ERASE];

    public static function assertValid(string $type): void
    {
        if (!in_array($type, self::ALL, true)) {
            throw new InvalidArgumentException(sprintf('Unsupported GDPR request type "%s".', $type));
        }
    }

    private function __construct()
    {
    }
}
