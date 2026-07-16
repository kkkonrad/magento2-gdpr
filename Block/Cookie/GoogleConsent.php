<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Cookie;

use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class GoogleConsent extends Template
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

    public function isEnabled(): bool
    {
        return $this->featureManager->isEnabled(
            FeatureCode::GOOGLE_CONSENT,
            (int)$this->storeManager->getStore()->getId()
        );
    }

    /** @return array<string, string|int> */
    public function getDefaultState(): array
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $state = [];
        foreach ([
            'ad_storage', 'analytics_storage', 'functionality_storage', 'personalization_storage',
            'security_storage', 'ad_user_data', 'ad_personalization',
        ] as $type) {
            $value = (string)$this->scopeConfig->getValue(
                'kkkonrad_gdpr/google_consent/' . $type,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
            $state[$type] = $value === 'granted' ? 'granted' : 'denied';
        }
        $state['wait_for_update'] = 500;

        return $state;
    }

    public function isDebugEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            'kkkonrad_gdpr/google_consent/debug_enabled',
            ScopeInterface::SCOPE_STORE,
            (int)$this->storeManager->getStore()->getId()
        );
    }
}
