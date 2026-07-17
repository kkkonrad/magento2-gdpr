<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Plugin\Adminhtml;

use Kkkonrad\Gdpr\Plugin\Adminhtml\ApplyConfigurationProfilePlugin;
use Magento\Config\Model\Config;
use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\TestCase;

class ApplyConfigurationProfilePluginTest extends TestCase
{
    public function testStrictProfileIsAppliedOnceWithoutEnablingMasterSwitch(): void
    {
        $config = $this->createMock(Config::class);
        $groups = [
            'general' => ['fields' => [
                'enabled' => ['value' => '0'],
                'configuration_profile' => ['value' => 'eu_strict'],
                'configuration_profile_ack' => ['value' => '1'],
            ]],
        ];
        $config->method('getData')->willReturnCallback(
            static fn (string $key): mixed => $key === 'section' ? 'kkkonrad_gdpr' : $groups
        );
        $saved = null;
        $config->expects(self::once())->method('setData')->with('groups', self::isType('array'))->willReturnCallback(
            static function (string $key, array $groups) use (&$saved, $config): Config {
                unset($key);
                $saved = $groups;
                return $config;
            }
        );

        (new ApplyConfigurationProfilePlugin())->beforeSave($config);

        self::assertSame('0', $saved['general']['fields']['enabled']['value']);
        self::assertSame('none', $saved['general']['fields']['configuration_profile']['value']);
        self::assertSame('1', $saved['cookie']['fields']['enabled']['value']);
        self::assertSame('deny_optional', $saved['cookie']['fields']['disabled_fallback']['value']);
        self::assertSame('basic', $saved['google_consent']['fields']['implementation_mode']['value']);
    }

    public function testProfileRequiresExplicitApproval(): void
    {
        $config = $this->createMock(Config::class);
        $groups = [
            'general' => ['fields' => [
                'configuration_profile' => ['value' => 'global_notice'],
                'configuration_profile_ack' => ['value' => '0'],
            ]],
        ];
        $config->method('getData')->willReturnCallback(
            static fn (string $key): mixed => $key === 'section' ? 'kkkonrad_gdpr' : $groups
        );

        $this->expectException(LocalizedException::class);
        (new ApplyConfigurationProfilePlugin())->beforeSave($config);
    }
}
