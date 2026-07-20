<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Cookie;

use DomainException;
use Kkkonrad\Gdpr\Api\Cookie\CookieRegistryInterface;
use Kkkonrad\Gdpr\Domain\Cookie\DecisionToken;
use Magento\Framework\Stdlib\CookieManagerInterface;

class CookieDecisionStateProvider
{
    public const COOKIE_NAME = 'kkkonrad_gdpr_consent';

    public function __construct(
        private readonly CookieManagerInterface $cookieManager,
        private readonly DecisionToken $decisionToken,
        private readonly CookieRegistryInterface $cookieRegistry
    ) {
    }

    /**
     * @return array{policy:string, choices:array<string, bool>, expires_at:int}|null
     */
    public function getVerifiedDecision(int $storeId, string $currentPolicyId): ?array
    {
        $token = $this->cookieManager->getCookie(self::COOKIE_NAME);
        if (!is_string($token) || $token === '') {
            return null;
        }

        try {
            $payload = $this->decisionToken->verify($token);
        } catch (DomainException|\JsonException) {
            return null;
        }

        if ((int)($payload['store_id'] ?? -1) !== $storeId
            || !is_string($payload['policy'] ?? null)
            || !hash_equals($currentPolicyId, $payload['policy'])
            || !is_array($payload['choices'] ?? null)
        ) {
            return null;
        }

        $choices = $this->normalizeChoices($storeId, $payload['choices']);
        if ($choices === null) {
            return null;
        }

        return [
            'policy' => $currentPolicyId,
            'choices' => $choices,
            'expires_at' => (int)$payload['expires_at'],
        ];
    }

    /**
     * @param array<mixed> $payloadChoices
     * @return array<string, bool>|null
     */
    private function normalizeChoices(int $storeId, array $payloadChoices): ?array
    {
        $normalized = [];
        $knownCodes = [];
        foreach ($this->cookieRegistry->getGroups($storeId) as $group) {
            $code = (string)$group['code'];
            $knownCodes[] = $code;
            $value = $payloadChoices[$code] ?? null;
            if (!is_bool($value) || ((bool)$group['is_required'] && !$value)) {
                return null;
            }
            $normalized[$code] = $value;
        }

        foreach (array_keys($payloadChoices) as $code) {
            if (!is_string($code) || !in_array($code, $knownCodes, true)) {
                return null;
            }
        }

        return $normalized;
    }
}
