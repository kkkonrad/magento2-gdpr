<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class AbandonedAccountReference implements OptionSourceInterface
{
    /** @return array<int, array{value:string,label:\Magento\Framework\Phrase}> */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'last_order_or_created', 'label' => __('Last order, otherwise account creation')],
            ['value' => 'last_login_or_created', 'label' => __('Last login, otherwise account creation')],
            ['value' => 'latest_activity', 'label' => __('Latest order, login or account update')],
        ];
    }
}
