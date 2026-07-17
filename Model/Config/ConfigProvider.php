<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Config;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Api\ConfigProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class ConfigProvider implements ConfigProviderInterface
{
    private const PREFIX = 'kkkonrad_gdpr/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function getString(string $path, int|string|null $scopeCode = null): string
    {
        $this->assertPath($path);
        return trim((string)$this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE, $scopeCode));
    }

    public function getPositiveInt(
        string $path,
        int|string|null $scopeCode = null,
        int $default = 1,
        int $maximum = 36500
    ): int {
        $value = (int)$this->getString($path, $scopeCode);
        return max(1, min(max(1, $maximum), $value > 0 ? $value : max(1, $default)));
    }

    public function getCsv(string $path, int|string|null $scopeCode = null): array
    {
        return array_values(array_unique(array_filter(array_map(
            'trim',
            explode(',', $this->getString($path, $scopeCode))
        ))));
    }

    private function assertPath(string $path): void
    {
        if (!str_starts_with($path, self::PREFIX) || preg_match('#^[a-z0-9_/]+$#', $path) !== 1) {
            throw new InvalidArgumentException('Only typed Kkkonrad GDPR configuration paths are allowed.');
        }
    }
}
