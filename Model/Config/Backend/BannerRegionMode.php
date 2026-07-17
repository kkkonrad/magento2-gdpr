<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Backend;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class BannerRegionMode extends Value
{
    public function beforeSave(): self
    {
        if ((string)$this->getValue() !== 'selected') {
            return parent::beforeSave();
        }
        $fieldsetData = $this->getData('fieldset_data');
        $regions = is_array($fieldsetData) && array_key_exists('banner_regions', $fieldsetData)
            ? $fieldsetData['banner_regions']
            : null;
        if ($regions === null) {
            $regions = $this->_config->getValue(
                'kkkonrad_gdpr/cookie/banner_regions',
                $this->getScope() !== '' ? $this->getScope() : ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                $this->getScopeId()
            );
        }
        if (trim((string)$regions) === '') {
            throw new LocalizedException(__('Selected-region banner mode requires at least one ISO region code.'));
        }
        return parent::beforeSave();
    }
}
