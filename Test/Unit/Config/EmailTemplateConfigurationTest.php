<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Config;

use PHPUnit\Framework\TestCase;
use SimpleXMLElement;

class EmailTemplateConfigurationTest extends TestCase
{
    public function testEveryEmailConfigFieldHasMagentoConventionTemplate(): void
    {
        $moduleDirectory = dirname(__DIR__, 3);
        $system = $this->loadXml($moduleDirectory . '/etc/adminhtml/system.xml');
        $templates = $this->loadXml($moduleDirectory . '/etc/email_templates.xml');
        $defaults = $this->loadXml($moduleDirectory . '/etc/config.xml');

        $registeredTemplates = [];
        foreach ($templates->template as $template) {
            $registeredTemplates[(string)$template['id']] = (string)$template['file'];
        }

        $templateFields = 0;
        foreach ($system->xpath('//group[@id="email"]/field') ?: [] as $field) {
            if ((string)$field->source_model !== 'Magento\Config\Model\Config\Source\Email\Template') {
                continue;
            }
            $templateFields++;
            $fieldId = (string)$field['id'];
            $expectedTemplateId = 'kkkonrad_gdpr_email_' . $fieldId;
            self::assertArrayHasKey($expectedTemplateId, $registeredTemplates);
            self::assertSame(
                $expectedTemplateId,
                (string)$defaults->default->kkkonrad_gdpr->email->{$fieldId}
            );
            self::assertFileExists(
                $moduleDirectory . '/view/frontend/email/' . $registeredTemplates[$expectedTemplateId]
            );
        }

        self::assertSame(8, $templateFields);
    }

    private function loadXml(string $path): SimpleXMLElement
    {
        $xml = simplexml_load_file($path);
        self::assertInstanceOf(SimpleXMLElement::class, $xml);

        return $xml;
    }
}
