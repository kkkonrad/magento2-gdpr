<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Cookie;

use Kkkonrad\Gdpr\Api\Cookie\CookiePolicyVersionProviderInterface;
use Kkkonrad\Gdpr\Api\Cookie\CookieRegistryInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Cmp extends Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Template\Context $context,
        private readonly FeatureManagerInterface $featureManager,
        private readonly CookieRegistryInterface $cookieRegistry,
        private readonly CookiePolicyVersionProviderInterface $policyVersionProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function isEnabled(): bool
    {
        return $this->featureManager->isEnabled(
            FeatureCode::COOKIE,
            (int)$this->storeManager->getStore()->getId()
        );
    }

    public function getJsonConfig(): string
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $policy = $this->policyVersionProvider->getOrPublishCurrent($storeId);

        return $this->json->serialize([
            'endpoint' => $this->getUrl('gdpr/consent/save'),
            'rejectedEndpoint' => $this->getUrl('gdpr/rejected/report'),
            'regionEndpoint' => $this->getUrl('gdpr/region/resolve'),
            'policy' => $policy['public_id'],
            'groups' => $this->getLocalizedGroups($storeId),
            'showBanner' => $this->featureManager->isEnabled(FeatureCode::COOKIE_BANNER, $storeId),
            'lockScreen' => $this->scopeConfig->isSetFlag(
                'kkkonrad_gdpr/cookie/lock_screen_enabled',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            'privacyUrl' => $this->getUrl('privacy-policy-cookie-restriction-mode'),
            'regionMode' => (string)$this->scopeConfig->getValue(
                'kkkonrad_gdpr/cookie/banner_region_mode',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            'regions' => array_values(array_filter(array_map('trim', explode(',', (string)$this->scopeConfig->getValue(
                'kkkonrad_gdpr/cookie/banner_regions',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ))))),
            'googleConsentEnabled' => $this->featureManager->isEnabled(FeatureCode::GOOGLE_CONSENT, $storeId),
            'googleConsentDebug' => $this->scopeConfig->isSetFlag(
                'kkkonrad_gdpr/google_consent/debug_enabled',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            'rejectedTrackingEnabled' => $this->featureManager->isEnabled(
                FeatureCode::COOKIE_REJECTED_TRACKING,
                $storeId
            ),
            'text' => [
                'banner' => (string)$this->scopeConfig->getValue(
                    'kkkonrad_gdpr/cookie/banner_text',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                ) ?: (string)__('We use essential cookies and, with your permission, optional cookies.'),
                'modalTitle' => (string)$this->scopeConfig->getValue(
                    'kkkonrad_gdpr/cookie/modal_title',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                ) ?: (string)__('Cookie settings'),
                'modalText' => (string)$this->scopeConfig->getValue(
                    'kkkonrad_gdpr/cookie/modal_text',
                    ScopeInterface::SCOPE_STORE,
                    $storeId
                ) ?: (string)__('Choose which optional cookie groups you allow.'),
                'acceptAll' => (string)__('Accept all'),
                'rejectOptional' => (string)__('Reject optional'),
                'customize' => (string)__('Customize'),
                'save' => (string)__('Save preferences'),
                'close' => (string)__('Close'),
                'settings' => (string)__('Cookie settings'),
                'required' => (string)__('Required'),
                'error' => (string)__('The cookie preference could not be saved. Please try again.'),
                'privacy' => (string)__('Privacy policy'),
                'cookieDetails' => (string)__('Show cookie details'),
            ],
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function getLocalizedGroups(int $storeId): array
    {
        $groups = $this->cookieRegistry->getGroups($storeId);
        foreach ($groups as &$group) {
            $group['name'] = (string)__((string)($group['name'] ?? ''));
            $group['description'] = (string)__((string)($group['description'] ?? ''));
            if (!isset($group['cookies']) || !is_array($group['cookies'])) {
                continue;
            }
            foreach ($group['cookies'] as &$cookie) {
                $cookie['name'] = (string)__((string)($cookie['name'] ?? ''));
                $cookie['description'] = (string)__((string)($cookie['description'] ?? ''));
            }
            unset($cookie);
        }
        unset($group);

        return $groups;
    }
}
