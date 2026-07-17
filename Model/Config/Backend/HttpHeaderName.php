<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class HttpHeaderName extends Value
{
    public function beforeSave(): self
    {
        $value = trim((string)$this->getValue());
        if (preg_match('/^[A-Za-z0-9-]{1,64}$/', $value) !== 1) {
            throw new LocalizedException(__('A trusted proxy header name contains unsupported characters.'));
        }
        $this->setValue($value);
        return parent::beforeSave();
    }
}
