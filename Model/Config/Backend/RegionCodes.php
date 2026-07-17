<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class RegionCodes extends Value
{
    public function beforeSave(): self
    {
        $codes = array_values(array_unique(array_filter(array_map(
            static fn (string $value): string => strtoupper(trim($value)),
            explode(',', (string)$this->getValue())
        ))));
        foreach ($codes as $code) {
            if (preg_match('/^[A-Z]{2}(?:-[A-Z0-9]{1,3})?$/', $code) !== 1) {
                throw new LocalizedException(__('Region code "%1" must use ISO country or subdivision syntax.', $code));
            }
        }
        $this->setValue(implode(',', $codes));
        return parent::beforeSave();
    }
}
