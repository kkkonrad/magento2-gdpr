<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Cron;

use Kkkonrad\Gdpr\Application\JobRunner;
use Magento\Framework\App\Config\ScopeConfigInterface;

final class ProcessJobs
{
    private const XML_PATH_BATCH_SIZE = 'kkkonrad_gdpr/data_rights/batch_size';

    public function __construct(
        private readonly JobRunner $jobRunner,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function execute(): void
    {
        $limit = max(1, (int)$this->scopeConfig->getValue(self::XML_PATH_BATCH_SIZE));
        $this->jobRunner->run($limit);
    }
}
