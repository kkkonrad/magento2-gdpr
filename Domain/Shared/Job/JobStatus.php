<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Shared\Job;

final class JobStatus
{
    public const QUEUED = 'queued';
    public const CLAIMED = 'claimed';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const PARTIALLY_COMPLETED = 'partially_completed';
    public const FAILED = 'failed';

    private function __construct()
    {
    }
}
