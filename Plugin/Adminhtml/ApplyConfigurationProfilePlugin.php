<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Plugin\Adminhtml;

use Magento\Config\Model\Config;
use Magento\Framework\Exception\LocalizedException;

class ApplyConfigurationProfilePlugin
{
    /**
     * @return array{Config}
     */
    public function beforeSave(Config $subject): array
    {
        if ((string)$subject->getData('section') !== 'kkkonrad_gdpr') {
            return [$subject];
        }
        $groups = $subject->getData('groups');
        if (!is_array($groups)) {
            return [$subject];
        }
        $generalFields = $groups['general']['fields'] ?? [];
        $profile = is_array($generalFields)
            ? (string)($generalFields['configuration_profile']['value'] ?? 'none')
            : 'none';
        if ($profile === 'none' || $profile === '') {
            return [$subject];
        }
        if ((string)($generalFields['configuration_profile_ack']['value'] ?? '0') !== '1') {
            throw new LocalizedException(
                __('Applying a GDPR configuration profile requires explicit administrative approval.')
            );
        }
        $values = $this->profileValues($profile);
        foreach ($values as $group => $fields) {
            foreach ($fields as $field => $value) {
                $groups[$group]['fields'][$field] = ['value' => $value];
            }
        }
        // Profiles are one-shot actions. Saving them as selected would unexpectedly reapply
        // their values on every future configuration change.
        $groups['general']['fields']['configuration_profile'] = ['value' => 'none'];
        $groups['general']['fields']['configuration_profile_ack'] = ['value' => '0'];
        $subject->setData('groups', $groups);
        return [$subject];
    }

    /** @return array<string, array<string, string>> */
    private function profileValues(string $profile): array
    {
        $strictDefaults = [
            'cookie' => [
                'enabled' => '1',
                'disabled_fallback' => 'deny_optional',
                'disabled_fallback_legal_ack' => '0',
                'banner_enabled' => '1',
                'geolocation_enabled' => '0',
                'banner_region_mode' => 'global',
                'lock_screen_enabled' => '0',
                'lock_screen_legal_ack' => '0',
            ],
            'google_consent' => [
                'enabled' => '1',
                'implementation_mode' => 'basic',
                'default_profile' => 'essential',
                'ad_storage' => 'denied',
                'analytics_storage' => 'denied',
                'functionality_storage' => 'denied',
                'personalization_storage' => 'denied',
                'security_storage' => 'granted',
                'ad_user_data' => 'denied',
                'ad_personalization' => 'denied',
            ],
        ];
        if ($profile === 'eu_strict') {
            return $strictDefaults;
        }
        if ($profile === 'global_notice') {
            return [
                'cookie' => [
                    'enabled' => '1',
                    'disabled_fallback' => 'allow_unmanaged',
                    'disabled_fallback_legal_ack' => '1',
                    'banner_enabled' => '1',
                    'geolocation_enabled' => '0',
                    'banner_region_mode' => 'global',
                    'lock_screen_enabled' => '0',
                    'lock_screen_legal_ack' => '0',
                ],
                'google_consent' => [
                    'enabled' => '0',
                    'default_profile' => 'essential',
                ],
            ];
        }
        throw new LocalizedException(__('Unknown GDPR configuration profile.'));
    }
}
