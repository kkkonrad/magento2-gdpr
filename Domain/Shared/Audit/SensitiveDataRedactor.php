<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Shared\Audit;

class SensitiveDataRedactor
{
    private const SENSITIVE_KEY_PATTERN = '/password|token|secret|authorization|cookie.?value|email|telephone|firstname|lastname|street|postcode|vat/i';

    /**
     * @param array<string|int, mixed> $metadata
     * @return array<string|int, mixed>
     */
    public function redact(array $metadata): array
    {
        $redacted = [];
        foreach ($metadata as $key => $value) {
            if (preg_match(self::SENSITIVE_KEY_PATTERN, (string)$key) === 1) {
                $redacted[$key] = '[REDACTED]';
                continue;
            }

            if (is_array($value)) {
                $redacted[$key] = $this->redact($value);
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $redacted[$key] = is_string($value) ? $this->redactString($value) : $value;
            }
        }

        return $redacted;
    }

    private function redactString(string $value): string
    {
        $value = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[REDACTED_EMAIL]', $value) ?? $value;
        $value = preg_replace('/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i', 'Bearer [REDACTED]', $value) ?? $value;
        $value = preg_replace('/\b(?:token|secret|password|cookie)\s*[=:]\s*[^\s,;]+/i', '$1=[REDACTED]', $value) ?? $value;

        return $value;
    }
}
