<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Backend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class AllowUnmanagedFallback extends Value
{
    public function beforeSave(): self
    {
        if ((string)$this->getValue() !== 'allow_unmanaged') {
            return parent::beforeSave();
        }
        $fieldsetData = $this->getData('fieldset_data');
        $acknowledged = is_array($fieldsetData) && array_key_exists('disabled_fallback_legal_ack', $fieldsetData)
            ? $fieldsetData['disabled_fallback_legal_ack']
            : $this->_config->getValue(
                'kkkonrad_gdpr/cookie/disabled_fallback_legal_ack',
                $this->getScope() !== '' ? $this->getScope() : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $this->getScopeId()
            );
        if ((string)$acknowledged !== '1') {
            throw new LocalizedException(
                __('Allowing unmanaged optional integrations requires explicit confirmation of legal approval.')
            );
        }
        return parent::beforeSave();
    }
}
