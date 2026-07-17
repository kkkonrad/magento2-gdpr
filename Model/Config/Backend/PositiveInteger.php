<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class PositiveInteger extends Value
{
    public function beforeSave(): self
    {
        $raw = $this->getValue();
        if (filter_var($raw, FILTER_VALIDATE_INT) === false || (int)$raw < 1) {
            throw new LocalizedException(__('The value for %1 must be a positive integer.', $this->getPath()));
        }
        $maximum = str_ends_with($this->getPath(), 'batch_size') ? 5000 : 36500;
        if ((int)$raw > $maximum) {
            throw new LocalizedException(__('The value for %1 cannot exceed %2.', $this->getPath(), $maximum));
        }
        $this->setValue((string)(int)$raw);
        return parent::beforeSave();
    }
}
