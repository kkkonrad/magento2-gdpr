<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api;

interface JobSchedulerInterface
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $configSnapshot
     */
    public function schedule(
        string $type,
        string $featureCode,
        int $storeId,
        array $payload = [],
        ?int $requestId = null,
        ?string $idempotencyKey = null,
        array $configSnapshot = []
    ): int;
}
