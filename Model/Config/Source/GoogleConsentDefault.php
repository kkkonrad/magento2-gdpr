<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

final class GoogleConsentDefault implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'denied', 'label' => __('Denied for all optional storage (recommended)')],
            ['value' => 'essential', 'label' => __('Granted for security storage only')],
            ['value' => 'custom', 'label' => __('Custom')],
        ];
    }
}
