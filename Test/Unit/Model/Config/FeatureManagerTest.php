<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Model\Config;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Kkkonrad\Gdpr\Model\Config\FeatureManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FeatureManagerTest extends TestCase
{
    private ScopeConfigInterface&MockObject $scopeConfig;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
    }

    public function testMasterSwitchDisablesEveryChildFeature(): void
    {
        $this->scopeConfig->expects(self::once())
            ->method('isSetFlag')
            ->with('kkkonrad_gdpr/general/enabled', ScopeInterface::SCOPE_STORE, 3)
            ->willReturn(false);

        self::assertFalse((new FeatureManager($this->scopeConfig))->isEnabled(FeatureCode::EXPORT_REQUEST, 3));
    }

    public function testFeatureRequiresMasterAndOwnSwitch(): void
    {
        $this->scopeConfig->expects(self::exactly(2))
            ->method('isSetFlag')
            ->willReturnMap([
                ['kkkonrad_gdpr/general/enabled', ScopeInterface::SCOPE_STORE, 2, true],
                ['kkkonrad_gdpr/data_rights/export_request_enabled', ScopeInterface::SCOPE_STORE, 2, true],
            ]);

        self::assertTrue((new FeatureManager($this->scopeConfig))->isEnabled(FeatureCode::EXPORT_REQUEST, 2));
    }

    public function testConsentLocationRequiresConsentParent(): void
    {
        $this->scopeConfig->expects(self::exactly(2))
            ->method('isSetFlag')
            ->willReturnMap([
                ['kkkonrad_gdpr/general/enabled', ScopeInterface::SCOPE_STORE, null, true],
                ['kkkonrad_gdpr/consent/enabled', ScopeInterface::SCOPE_STORE, null, false],
            ]);

        self::assertFalse(
            (new FeatureManager($this->scopeConfig))->isEnabled(FeatureCode::CONSENT_CHECKOUT)
        );
    }

    public function testGoogleConsentDoesNotRequireCookieFeature(): void
    {
        $this->scopeConfig->expects(self::exactly(2))
            ->method('isSetFlag')
            ->willReturnMap([
                ['kkkonrad_gdpr/general/enabled', ScopeInterface::SCOPE_STORE, null, true],
                ['kkkonrad_gdpr/google_consent/enabled', ScopeInterface::SCOPE_STORE, null, true],
            ]);

        self::assertTrue((new FeatureManager($this->scopeConfig))->isEnabled(FeatureCode::GOOGLE_CONSENT));
    }

    public function testUnknownFeatureIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown GDPR feature code');

        (new FeatureManager($this->scopeConfig))->isEnabled('not-a-feature');
    }
}
