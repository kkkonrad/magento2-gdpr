<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Infrastructure\Logging;

use Magento\Framework\Logger\Monolog;

class Logger extends Monolog
{
    public function __construct(Handler $handler, RedactionProcessor $redactionProcessor)
    {
        parent::__construct('kkkonrad_gdpr', [$handler], [$redactionProcessor]);
    }
}
