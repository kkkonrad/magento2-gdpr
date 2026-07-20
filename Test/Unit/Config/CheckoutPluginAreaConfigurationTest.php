<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Config;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class CheckoutPluginAreaConfigurationTest extends TestCase
{
    public function testCheckoutApiPluginsAreRegisteredGloballyForRestCheckout(): void
    {
        $moduleDirectory = dirname(__DIR__, 3);
        $global = $this->loadXml($moduleDirectory . '/etc/di.xml');
        $frontend = $this->loadXml($moduleDirectory . '/etc/frontend/di.xml');
        $pluginNames = [
            'kkkonrad_gdpr_checkout_consent',
            'kkkonrad_gdpr_guest_checkout_consent',
        ];

        foreach ($pluginNames as $pluginName) {
            self::assertCount(1, $global->xpath(sprintf('//plugin[@name="%s"]', $pluginName)) ?: []);
            self::assertCount(0, $frontend->xpath(sprintf('//plugin[@name="%s"]', $pluginName)) ?: []);
        }
    }

    private function loadXml(string $path): SimpleXMLElement
    {
        $xml = simplexml_load_file($path);
        self::assertInstanceOf(SimpleXMLElement::class, $xml);

        return $xml;
    }
}
