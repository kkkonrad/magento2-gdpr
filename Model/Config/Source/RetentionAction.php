<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class RetentionAction implements OptionSourceInterface
{
    /** @return array<int, array{value:string,label:\Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'anonymize', 'label' => __('Anonymize (recommended)')],
            ['value' => 'erase', 'label' => __('Erase account and erasable data')],
        ];
    }
}
