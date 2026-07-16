<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Cookie;

use DomainException;
use Magento\Framework\App\DeploymentConfig;

class DecisionToken
{
    private const SIGNATURE_ALGORITHM = 'sha256';

    public function __construct(private readonly DeploymentConfig $deploymentConfig)
    {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload): string
    {
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac(self::SIGNATURE_ALGORITHM, $encodedPayload, $this->getKey(), true);

        return $encodedPayload . '.' . $this->base64UrlEncode($signature);
    }

    /**
     * @return array<string, mixed>
     */
    public function verify(string $token): array
    {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            throw new DomainException('Malformed cookie consent token.');
        }
        [$encodedPayload, $encodedSignature] = $parts;
        $expected = hash_hmac(self::SIGNATURE_ALGORITHM, $encodedPayload, $this->getKey(), true);
        $signature = $this->base64UrlDecode($encodedSignature);
        if (!hash_equals($expected, $signature)) {
            throw new DomainException('Invalid cookie consent token signature.');
        }
        $payload = json_decode($this->base64UrlDecode($encodedPayload), true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($payload) || (int)($payload['expires_at'] ?? 0) < time()) {
            throw new DomainException('Cookie consent token has expired.');
        }

        return $payload;
    }

    private function getKey(): string
    {
        $key = (string)$this->deploymentConfig->get('crypt/key');
        if ($key === '') {
            throw new DomainException('Magento crypt key is unavailable.');
        }

        return hash('sha256', 'kkkonrad-gdpr-cookie|' . $key, true);
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;
        if ($padding !== 0) {
            $value .= str_repeat('=', 4 - $padding);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new DomainException('Malformed cookie consent token encoding.');
        }

        return $decoded;
    }
}
