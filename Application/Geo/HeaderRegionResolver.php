<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Geo;

use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\Geo\RegionResolverInterface;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class HeaderRegionResolver implements RegionResolverInterface
{
    public function __construct(
        private readonly FeatureManagerInterface $featureManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly Http $request,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function resolve(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->featureManager->isEnabled(FeatureCode::COOKIE_GEOLOCATION, $storeId)) {
            return ['region' => null, 'source' => 'disabled', 'confidence' => 'none'];
        }
        $countryHeader = (string)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/cookie/geo_country_header',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $regionHeader = (string)$this->scopeConfig->getValue(
            'kkkonrad_gdpr/cookie/geo_region_header',
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $country = strtoupper((string)$this->request->getHeader($countryHeader));
        $subdivision = strtoupper((string)$this->request->getHeader($regionHeader));
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            return ['region' => null, 'source' => 'strict_fallback', 'confidence' => 'none'];
        }
        $region = $country;
        if (preg_match('/^[A-Z0-9]{1,3}$/', $subdivision) === 1) {
            $region .= '-' . $subdivision;
        }

        return ['region' => $region, 'source' => 'trusted_proxy_header', 'confidence' => 'medium'];
    }
}
