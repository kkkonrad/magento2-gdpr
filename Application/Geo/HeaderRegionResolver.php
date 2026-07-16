<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Geo;

use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Api\Geo\RegionResolverInterface;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\Request\Http;
use Magento\Store\Model\StoreManagerInterface;

class HeaderRegionResolver implements RegionResolverInterface
{
    public function __construct(
        private readonly FeatureManagerInterface $featureManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly Http $request
    ) {
    }

    public function resolve(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->featureManager->isEnabled(FeatureCode::COOKIE_GEOLOCATION, $storeId)) {
            return ['region' => null, 'source' => 'disabled', 'confidence' => 'none'];
        }
        $country = strtoupper((string)($this->request->getHeader('CF-IPCountry')
            ?: $this->request->getHeader('X-Country-Code')));
        $subdivision = strtoupper((string)($this->request->getHeader('CF-Region-Code')
            ?: $this->request->getHeader('X-Region-Code')));
        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            return ['region' => null, 'source' => 'strict_fallback', 'confidence' => 'none'];
        }
        $region = $country;
        if ($country === 'US' && preg_match('/^[A-Z0-9]{1,3}$/', $subdivision) === 1) {
            $region .= '-' . $subdivision;
        }

        return ['region' => $region, 'source' => 'trusted_proxy_header', 'confidence' => 'medium'];
    }
}
