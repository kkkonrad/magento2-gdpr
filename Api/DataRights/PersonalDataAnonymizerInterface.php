<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\DataRights;

interface PersonalDataAnonymizerInterface
{
    public function getCode(): string;

    public function getPriority(): int;

    /** @return array<string, int> Processed entity counters without personal values. */
    public function anonymize(int $customerId, string $operationKey): array;
}
