<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Consent;

use DomainException;

class ConsentLocation
{
    public const REGISTRATION = 'registration';
    public const NEWSLETTER = 'newsletter';
    public const CONTACT = 'contact';
    public const CHECKOUT = 'checkout';

    public const ALL = [
        self::REGISTRATION,
        self::NEWSLETTER,
        self::CONTACT,
        self::CHECKOUT,
    ];

    public static function assertValid(string $location): void
    {
        if (!in_array($location, self::ALL, true)) {
            throw new DomainException(sprintf('Unsupported consent location "%s".', $location));
        }
    }

    private function __construct()
    {
    }
}
