<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Consent;

use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;

class ConsentFeatureResolver
{
    public function forLocation(string $location): string
    {
        ConsentLocation::assertValid($location);

        return match ($location) {
            ConsentLocation::REGISTRATION => FeatureCode::CONSENT_REGISTRATION,
            ConsentLocation::NEWSLETTER => FeatureCode::CONSENT_NEWSLETTER,
            ConsentLocation::CONTACT => FeatureCode::CONSENT_CONTACT,
            ConsentLocation::CHECKOUT => FeatureCode::CONSENT_CHECKOUT,
            default => throw new \DomainException('Unsupported consent location.'),
        };
    }
}
