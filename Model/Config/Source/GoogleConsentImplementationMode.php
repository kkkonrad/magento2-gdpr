<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class GoogleConsentImplementationMode implements OptionSourceInterface
{
    /** @return array<int, array{value:string, label:\Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'advanced', 'label' => __('Advanced mode')],
            ['value' => 'basic', 'label' => __('Basic mode (tags must also use consent gating)')],
        ];
    }
}
