<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api;

interface JobSchedulerInterface
{
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
