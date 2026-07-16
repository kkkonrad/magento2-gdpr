<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Shared\Job;

interface JobProcessorInterface
{
    public function getType(): string;

    public function process(JobContext $context): void;
}
