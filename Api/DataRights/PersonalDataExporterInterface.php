<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\DataRights;

interface PersonalDataExporterInterface
{
    public function getCode(): string;

    public function getPriority(): int;

    /**
     * @return array<string, array{columns:string[],rows:array<int,array<string,mixed>>}>
     */
    public function export(int $customerId, int $storeId): array;
}
