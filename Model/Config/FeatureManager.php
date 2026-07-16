<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

final class FeatureManager implements FeatureManagerInterface
{
    private const XML_PATHS = [
        FeatureCode::MODULE => ['kkkonrad_gdpr/general/enabled'],
        FeatureCode::DASHBOARD => ['kkkonrad_gdpr/data_rights/dashboard_enabled'],
        FeatureCode::EXPORT_REQUEST => ['kkkonrad_gdpr/data_rights/export_request_enabled'],
        FeatureCode::ANONYMIZATION_REQUEST => ['kkkonrad_gdpr/data_rights/anonymization_request_enabled'],
        FeatureCode::ERASURE_REQUEST => ['kkkonrad_gdpr/data_rights/erasure_request_enabled'],
        FeatureCode::RETENTION_OLD_ORDERS => ['kkkonrad_gdpr/data_rights/retention_old_orders_enabled'],
        FeatureCode::RETENTION_ABANDONED_ACCOUNTS => ['kkkonrad_gdpr/data_rights/retention_abandoned_accounts_enabled'],
        FeatureCode::CONSENT => ['kkkonrad_gdpr/consent/enabled'],
        FeatureCode::CONSENT_REGISTRATION => [
            'kkkonrad_gdpr/consent/enabled',
            'kkkonrad_gdpr/consent/registration_enabled',
        ],
        FeatureCode::CONSENT_NEWSLETTER => [
            'kkkonrad_gdpr/consent/enabled',
            'kkkonrad_gdpr/consent/newsletter_enabled',
        ],
        FeatureCode::CONSENT_CONTACT => [
            'kkkonrad_gdpr/consent/enabled',
            'kkkonrad_gdpr/consent/contact_enabled',
        ],
        FeatureCode::CONSENT_CHECKOUT => [
            'kkkonrad_gdpr/consent/enabled',
            'kkkonrad_gdpr/consent/checkout_enabled',
        ],
        FeatureCode::COOKIE => ['kkkonrad_gdpr/cookie/enabled'],
        FeatureCode::COOKIE_BANNER => [
            'kkkonrad_gdpr/cookie/enabled',
            'kkkonrad_gdpr/cookie/banner_enabled',
        ],
        FeatureCode::COOKIE_REJECTED_TRACKING => [
            'kkkonrad_gdpr/cookie/enabled',
            'kkkonrad_gdpr/cookie/rejected_tracking_enabled',
        ],
        FeatureCode::COOKIE_GEOLOCATION => [
            'kkkonrad_gdpr/cookie/enabled',
            'kkkonrad_gdpr/cookie/geolocation_enabled',
        ],
        FeatureCode::GOOGLE_CONSENT => ['kkkonrad_gdpr/google_consent/enabled'],
    ];

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isEnabled(string $featureCode, int|string|null $scopeCode = null): bool
    {
        if (!isset(self::XML_PATHS[$featureCode])) {
            throw new InvalidArgumentException(sprintf('Unknown GDPR feature code "%s".', $featureCode));
        }

        $paths = self::XML_PATHS[$featureCode];
        if ($featureCode !== FeatureCode::MODULE) {
            array_unshift($paths, self::XML_PATHS[FeatureCode::MODULE][0]);
        }

        foreach (array_unique($paths) as $path) {
            if (!$this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $scopeCode)) {
                return false;
            }
        }

        return true;
    }
}
