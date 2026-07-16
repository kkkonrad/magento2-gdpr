<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Domain\Consent;

use Kkkonrad\Gdpr\Domain\Consent\ConsentFeatureResolver;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConsentFeatureResolverTest extends TestCase
{
    #[DataProvider('locationProvider')]
    public function testMapsLocationToFeature(string $location, string $feature): void
    {
        self::assertSame($feature, (new ConsentFeatureResolver())->forLocation($location));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function locationProvider(): array
    {
        return [
            'registration' => [ConsentLocation::REGISTRATION, FeatureCode::CONSENT_REGISTRATION],
            'newsletter' => [ConsentLocation::NEWSLETTER, FeatureCode::CONSENT_NEWSLETTER],
            'contact' => [ConsentLocation::CONTACT, FeatureCode::CONSENT_CONTACT],
            'checkout' => [ConsentLocation::CHECKOUT, FeatureCode::CONSENT_CHECKOUT],
        ];
    }
}
