<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Plugin\Adminhtml;

use Kkkonrad\Gdpr\Api\CorrelationIdProviderInterface;
use Kkkonrad\Gdpr\Application\Audit\AuditWriter;
use Kkkonrad\Gdpr\Plugin\Adminhtml\AuditConfigurationSavePlugin;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Config\Model\Config;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\Data\WebsiteInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AuditConfigurationSavePluginTest extends TestCase
{
    private StoreManagerInterface&MockObject $storeManager;
    private AuditConfigurationSavePlugin $plugin;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->plugin = new AuditConfigurationSavePlugin(
            $this->createStub(ResourceConnection::class),
            $this->storeManager,
            $this->createStub(AdminSession::class),
            $this->createStub(CorrelationIdProviderInterface::class),
            $this->createStub(AuditWriter::class)
        );
    }

    public function testNullScopeParametersResolveToDefault(): void
    {
        $config = $this->configWithScope(null, null);
        $this->storeManager->expects(self::never())->method('getStore');
        $this->storeManager->expects(self::never())->method('getWebsite');

        self::assertSame(['default', 0], $this->resolveScope($config));
    }

    public function testStoreCodeTakesPrecedence(): void
    {
        $config = $this->configWithScope(' store_pl ', 'base');
        $store = $this->createMock(StoreInterface::class);
        $store->method('getId')->willReturn(4);
        $this->storeManager->expects(self::once())->method('getStore')->with('store_pl')->willReturn($store);
        $this->storeManager->expects(self::never())->method('getWebsite');

        self::assertSame(['stores', 4], $this->resolveScope($config));
    }

    public function testWebsiteScopeIsResolvedWhenStoreIsEmpty(): void
    {
        $config = $this->configWithScope('', ' base ');
        $website = $this->createMock(WebsiteInterface::class);
        $website->method('getId')->willReturn(2);
        $this->storeManager->expects(self::once())->method('getWebsite')->with('base')->willReturn($website);

        self::assertSame(['websites', 2], $this->resolveScope($config));
    }

    private function configWithScope(mixed $store, mixed $website): Config&MockObject
    {
        $config = $this->getMockBuilder(Config::class)
            ->disableOriginalConstructor()
            ->addMethods(['getStore', 'getWebsite'])
            ->getMock();
        $config->method('getStore')->willReturn($store);
        $config->method('getWebsite')->willReturn($website);

        return $config;
    }

    /** @return array{string, int} */
    private function resolveScope(Config $config): array
    {
        $method = new ReflectionMethod($this->plugin, 'resolveScope');

        return $method->invoke($this->plugin, $config);
    }
}
