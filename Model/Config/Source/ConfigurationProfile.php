<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class ConfigurationProfile implements OptionSourceInterface
{
    /** @return array<int, array{value:string,label:\Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'none', 'label' => __('Do not apply a profile')],
            ['value' => 'eu_strict', 'label' => __('EU — strict / default denied')],
            ['value' => 'global_notice', 'label' => __('Global informational notice')],
        ];
    }
}
