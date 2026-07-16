<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Shared\Audit;

final class SensitiveDataRedactor
{
    private const SENSITIVE_KEY_PATTERN = '/password|token|secret|authorization|cookie.?value|email|telephone|firstname|lastname|street|postcode|vat/i';

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
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }
}
