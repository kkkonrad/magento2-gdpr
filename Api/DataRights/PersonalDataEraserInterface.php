<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\DataRights;

interface PersonalDataEraserInterface
{
    public function getCode(): string;

    public function getPriority(): int;

    /** @return array<string, int> Processed entity counters without personal values. */
    public function erase(int $customerId, string $operationKey): array;
}
