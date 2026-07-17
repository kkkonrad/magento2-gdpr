<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class BannerRegionMode implements OptionSourceInterface
{
    /** @return array<int, array{value:string,label:\Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'global', 'label' => __('Show globally (recommended strict default)')],
            ['value' => 'selected', 'label' => __('Show only in configured countries/regions')],
        ];
    }
}
