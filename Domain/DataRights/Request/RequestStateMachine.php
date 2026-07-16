<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\DataRights\Request;

use DomainException;

class RequestStateMachine
{
    private const TRANSITIONS = [
        RequestStatus::SUBMITTED => [RequestStatus::VALIDATION],
        RequestStatus::VALIDATION => [
            RequestStatus::BLOCKED,
            RequestStatus::PENDING_APPROVAL,
            RequestStatus::QUEUED,
        ],
        RequestStatus::PENDING_APPROVAL => [RequestStatus::REJECTED, RequestStatus::QUEUED],
        RequestStatus::QUEUED => [RequestStatus::PROCESSING],
        RequestStatus::PROCESSING => [
            RequestStatus::COMPLETED,
            RequestStatus::PARTIALLY_COMPLETED,
            RequestStatus::FAILED,
        ],
        RequestStatus::FAILED => [RequestStatus::QUEUED],
        RequestStatus::PARTIALLY_COMPLETED => [RequestStatus::QUEUED],
    ];

    public function assertCanTransition(string $currentStatus, string $targetStatus): void
    {
        if (!in_array($targetStatus, self::TRANSITIONS[$currentStatus] ?? [], true)) {
            throw new DomainException(
                sprintf('GDPR request cannot transition from "%s" to "%s".', $currentStatus, $targetStatus)
            );
        }
    }

    public function canTransition(string $currentStatus, string $targetStatus): bool
    {
        return in_array($targetStatus, self::TRANSITIONS[$currentStatus] ?? [], true);
    }
}
