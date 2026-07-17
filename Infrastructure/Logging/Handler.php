<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Infrastructure\Logging;

use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    protected $loggerType = Logger::INFO;

    protected $fileName = '/var/log/kkkonrad_gdpr.log';
}
