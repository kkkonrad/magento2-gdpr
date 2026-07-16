<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Erasure;

use Magento\Framework\Registry;

class SecureAreaExecutor
{
    public function __construct(private readonly Registry $registry)
    {
    }

    public function execute(callable $operation): mixed
    {
        $alreadySecure = (bool)$this->registry->registry('isSecureArea');
        if (!$alreadySecure) {
            $this->registry->register('isSecureArea', true);
        }
        try {
            return $operation();
        } finally {
            if (!$alreadySecure) {
                $this->registry->unregister('isSecureArea');
            }
        }
    }
}
