<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\Cookie;

interface CookieDecisionRecorderInterface
{
    /**
     * @param array<string, bool> $choices
     * @return array{event_id:int, subject_key:string, token:string, choices:array<string, bool>, expires_at:int}
     */
    public function record(
        int $storeId,
        array $choices,
        ?string $subjectKey = null,
        ?int $customerId = null,
        ?string $region = null,
        ?string $correlationId = null
    ): array;
}
