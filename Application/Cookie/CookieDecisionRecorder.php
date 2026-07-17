<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Cookie;

use DomainException;
use Kkkonrad\Gdpr\Api\ClockInterface;
use Kkkonrad\Gdpr\Api\CorrelationIdProviderInterface;
use Kkkonrad\Gdpr\Api\Cookie\CookieDecisionRecorderInterface;
use Kkkonrad\Gdpr\Api\Cookie\CookiePolicyVersionProviderInterface;
use Kkkonrad\Gdpr\Api\Cookie\CookieRegistryInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\Consent\SubjectKeyGenerator;
use Kkkonrad\Gdpr\Domain\Cookie\DecisionToken;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\ScopeInterface;

class CookieDecisionRecorder implements CookieDecisionRecorderInterface
{
    private const XML_PATH_LIFETIME = 'kkkonrad_gdpr/cookie/consent_lifetime_days';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly FeatureManagerInterface $featureManager,
        private readonly CookieRegistryInterface $cookieRegistry,
        private readonly CookiePolicyVersionProviderInterface $policyVersionProvider,
        private readonly SubjectKeyGenerator $subjectKeyGenerator,
        private readonly DecisionToken $decisionToken,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ClockInterface $clock,
        private readonly CorrelationIdProviderInterface $correlationIdProvider
    ) {
    }

    public function record(
        int $storeId,
        array $choices,
        ?string $subjectKey = null,
        ?int $customerId = null,
        ?string $region = null,
        ?string $correlationId = null
    ): array {
        if (!$this->featureManager->isEnabled(FeatureCode::COOKIE, $storeId)) {
            throw new DomainException('Cookie consent is disabled.');
        }
        if ($subjectKey === null) {
            $subjectKey = $this->subjectKeyGenerator->generate();
        } else {
            $this->subjectKeyGenerator->assertValid($subjectKey);
        }
        if ($region !== null && preg_match('/^[A-Z]{2}(?:-[A-Z0-9]{1,3})?$/', $region) !== 1) {
            throw new DomainException('Region must use an ISO country or subdivision code.');
        }

        $normalizedChoices = $this->normalizeChoices($storeId, $choices);
        $policy = $this->policyVersionProvider->getOrPublishCurrent($storeId);
        $choicesJson = json_encode($normalizedChoices, JSON_THROW_ON_ERROR);
        $eventTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie_consent_event');
        $connection = $this->resourceConnection->getConnection();
        $connection->insert($eventTable, [
            'policy_version_id' => $policy['policy_version_id'],
            'customer_id' => $customerId,
            'subject_key' => $subjectKey,
            'choices_json' => $choicesJson,
            'region' => $region,
            'store_id' => $storeId,
            'correlation_id' => mb_substr($correlationId ?? $this->correlationIdProvider->get(), 0, 64),
        ]);
        $eventId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
        $lifetimeDays = max(1, (int)$this->scopeConfig->getValue(
            self::XML_PATH_LIFETIME,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        $issuedAt = $this->clock->timestamp();
        $expiresAt = $issuedAt + ($lifetimeDays * 86400);
        $token = $this->decisionToken->create([
            'policy' => $policy['public_id'],
            'subject_key' => $subjectKey,
            'choices' => $normalizedChoices,
            'store_id' => $storeId,
            'issued_at' => $issuedAt,
            'expires_at' => $expiresAt,
        ]);

        return [
            'event_id' => $eventId,
            'subject_key' => $subjectKey,
            'token' => $token,
            'choices' => $normalizedChoices,
            'expires_at' => $expiresAt,
        ];
    }

    /**
     * @param array<string, bool> $choices
     * @return array<string, bool>
     */
    private function normalizeChoices(int $storeId, array $choices): array
    {
        $normalized = [];
        $knownCodes = [];
        foreach ($this->cookieRegistry->getGroups($storeId) as $group) {
            $code = (string)$group['code'];
            $knownCodes[] = $code;
            if ((bool)$group['is_required']) {
                $normalized[$code] = true;
                continue;
            }
            $value = $choices[$code] ?? false;
            if (!is_bool($value)) {
                throw new DomainException(sprintf('Cookie group "%s" choice must be boolean.', $code));
            }
            $normalized[$code] = $value;
        }
        $unknownCodes = array_diff(array_keys($choices), $knownCodes);
        if ($unknownCodes !== []) {
            throw new DomainException(sprintf('Unknown cookie group "%s".', reset($unknownCodes)));
        }

        return $normalized;
    }
}
