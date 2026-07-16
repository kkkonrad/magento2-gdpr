<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CookieDisabledFallback implements OptionSourceInterface
{
    public const DENY_OPTIONAL = 'deny_optional';
    public const ALLOW_UNMANAGED = 'allow_unmanaged';

    /** @return array<int, array{value:string, label:\Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::DENY_OPTIONAL, 'label' => __('Deny optional storage (recommended)')],
            ['value' => self::ALLOW_UNMANAGED, 'label' => __('Allow unmanaged tags and cookies')],
        ];
    }
}
