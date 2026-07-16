<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\DataRights\Request;

final class RequestStatus
{
    public const SUBMITTED = 'submitted';
    public const VALIDATION = 'validation';
    public const BLOCKED = 'blocked';
    public const PENDING_APPROVAL = 'pending_approval';
    public const REJECTED = 'rejected';
    public const QUEUED = 'queued';
    public const PROCESSING = 'processing';
    public const COMPLETED = 'completed';
    public const PARTIALLY_COMPLETED = 'partially_completed';
    public const FAILED = 'failed';

    public const TERMINAL = [self::BLOCKED, self::REJECTED, self::COMPLETED];

    private function __construct()
    {
    }
}
