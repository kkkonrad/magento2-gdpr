<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class GoogleConsentDefault implements OptionSourceInterface
{
    /** @return array<int, array{value:string, label:\Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'denied', 'label' => __('Denied')],
            ['value' => 'granted', 'label' => __('Granted')],
        ];
    }
}
