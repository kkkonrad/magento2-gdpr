<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api;

interface ConfigProviderInterface
{
    public function getString(string $path, int|string|null $scopeCode = null): string;

    public function getPositiveInt(
        string $path,
        int|string|null $scopeCode = null,
        int $default = 1,
        int $maximum = 36500
    ): int;

    /** @return string[] */
    public function getCsv(string $path, int|string|null $scopeCode = null): array;
}
