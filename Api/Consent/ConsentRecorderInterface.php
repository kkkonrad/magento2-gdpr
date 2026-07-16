<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\Consent;

interface ConsentRecorderInterface
{
    /**
     * Record a decision as a new append-only event.
     */
    public function record(
        int $versionId,
        string $decision,
        string $source,
        int $storeId,
        ?int $customerId = null,
        ?string $subjectKey = null,
        ?string $correlationId = null
    ): int;
}
