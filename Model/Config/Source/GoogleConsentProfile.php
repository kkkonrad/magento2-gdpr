<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class GoogleConsentProfile implements OptionSourceInterface
{
    /** @return array<int, array{value:string, label:\Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'all_denied', 'label' => __('All denied')],
            ['value' => 'essential', 'label' => __('Essential storage only (recommended)')],
            ['value' => 'custom', 'label' => __('Custom values below')],
        ];
    }
}
