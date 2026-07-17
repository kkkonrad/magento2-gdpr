<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Backend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class LockScreenEnabled extends Value
{
    public function beforeSave(): self
    {
        if ((string)$this->getValue() !== '1') {
            return parent::beforeSave();
        }
        $fieldsetData = $this->getData('fieldset_data');
        $acknowledged = is_array($fieldsetData) && array_key_exists('lock_screen_legal_ack', $fieldsetData)
            ? $fieldsetData['lock_screen_legal_ack']
            : null;
        if ($acknowledged === null) {
            $acknowledged = $this->_config->getValue(
                'kkkonrad_gdpr/cookie/lock_screen_legal_ack',
                $this->getScope() !== '' ? $this->getScope() : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $this->getScopeId()
            );
        }
        if ((string)$acknowledged !== '1') {
            throw new LocalizedException(
                __('Cookie wall mode requires explicit confirmation that legal approval was obtained.')
            );
        }
        return parent::beforeSave();
    }
}
