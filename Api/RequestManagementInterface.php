<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api;

interface RequestManagementInterface
{
    public function submit(
        int $customerId,
        string $type,
        int $storeId,
        ?string $subjectKey = null,
        string $actorType = 'customer',
        ?int $actorId = null
    ): int;

    /** @param array<string, mixed> $metadata */
    public function transition(
        int $requestId,
        string $targetStatus,
        string $actorType,
        ?int $actorId = null,
        ?string $publicReason = null,
        ?string $adminReason = null,
        array $metadata = []
    ): void;
}
