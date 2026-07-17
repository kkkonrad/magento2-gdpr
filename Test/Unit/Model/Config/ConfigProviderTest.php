<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Model\Config;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Model\Config\ConfigProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    public function testReturnsTypedValuesWithinModuleNamespace(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')
            ->with('kkkonrad_gdpr/data_rights/batch_size', ScopeInterface::SCOPE_STORE, 3)
            ->willReturn(' 250 ');

        $provider = new ConfigProvider($scopeConfig);

        self::assertSame('250', $provider->getString('kkkonrad_gdpr/data_rights/batch_size', 3));
        self::assertSame(100, $provider->getPositiveInt(
            'kkkonrad_gdpr/data_rights/batch_size',
            3,
            20,
            100
        ));
    }

    public function testCsvValuesAreTrimmedUniqueAndEmptyValuesRemoved(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('PL, DE,PL,,US-CA');

        $provider = new ConfigProvider($scopeConfig);

        self::assertSame(
            ['PL', 'DE', 'US-CA'],
            $provider->getCsv('kkkonrad_gdpr/cookie/banner_regions')
        );
    }

    public function testRejectsPathsOutsideModuleConfiguration(): void
    {
        $provider = new ConfigProvider($this->createStub(ScopeConfigInterface::class));

        $this->expectException(InvalidArgumentException::class);
        $provider->getString('web/secure/base_url');
    }

    public function testPositiveIntegerUsesSafeDefaultForInvalidValue(): void
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturn('0');

        $provider = new ConfigProvider($scopeConfig);

        self::assertSame(48, $provider->getPositiveInt(
            'kkkonrad_gdpr/data_rights/notification_ttl_hours',
            null,
            48,
            168
        ));
    }
}
