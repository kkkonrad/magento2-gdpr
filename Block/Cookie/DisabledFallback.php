<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Cookie;

use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Kkkonrad\Gdpr\Model\Config\Source\CookieDisabledFallback as FallbackSource;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class DisabledFallback extends Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Template\Context $context,
        private readonly FeatureManagerInterface $featureManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function shouldAllowUnmanaged(): bool
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        return $this->featureManager->isEnabled(FeatureCode::MODULE, $storeId)
            && !$this->featureManager->isEnabled(FeatureCode::COOKIE, $storeId)
            && $this->scopeConfig->getValue(
                'kkkonrad_gdpr/cookie/disabled_fallback',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) === FallbackSource::ALLOW_UNMANAGED;
    }
}
