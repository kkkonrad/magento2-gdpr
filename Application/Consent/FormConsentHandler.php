<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Consent;

use Kkkonrad\Gdpr\Api\Consent\ActiveConsentsProviderInterface;
use Kkkonrad\Gdpr\Api\Consent\ConsentRecorderInterface;
use Kkkonrad\Gdpr\Api\CorrelationIdProviderInterface;
use Kkkonrad\Gdpr\Domain\Consent\ConsentDecision;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Kkkonrad\Gdpr\Domain\Consent\SubjectKeyGenerator;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadataFactory;
use Magento\Store\Model\StoreManagerInterface;

class FormConsentHandler
{
    public const SUBJECT_COOKIE = 'kkkonrad_gdpr_form_subject';

    /** @var array<string, array<int, array<string, int|string|bool>>> */
    private array $validated = [];
    private ?string $guestSubjectKey = null;

    public function __construct(
        private readonly ActiveConsentsProviderInterface $activeConsentsProvider,
        private readonly ConsentRecorderInterface $consentRecorder,
        private readonly SubjectKeyGenerator $subjectKeyGenerator,
        private readonly Http $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly CorrelationIdProviderInterface $correlationIdProvider,
        private readonly CookieManagerInterface $cookieManager,
        private readonly PublicCookieMetadataFactory $cookieMetadataFactory
    ) {
    }

    public function validate(string $location): void
    {
        $submitted = $this->request->getParam('kkkonrad_gdpr_consent', []);
        if (!is_array($submitted)) {
            $submitted = [];
        }
        $this->validateSubmitted($location, $submitted);
    }

    /**
     * @param array<int|string, mixed> $submitted
     */
    public function validateSubmitted(string $location, array $submitted): void
    {
        ConsentLocation::assertValid($location);
        $storeId = (int)$this->storeManager->getStore()->getId();
        $definitions = $this->activeConsentsProvider->getForLocation($location, $storeId);
        if ($definitions === []) {
            $this->validated[$location] = [];
            return;
        }
        foreach ($definitions as &$definition) {
            $versionId = (int)$definition['version_id'];
            $accepted = isset($submitted[$versionId]) && (string)$submitted[$versionId] === '1';
            if ((bool)$definition['is_required'] && !$accepted) {
                throw new InputException(__('Please accept all required privacy consents.'));
            }
            $definition['decision'] = $accepted ? ConsentDecision::ACCEPTED : ConsentDecision::DECLINED;
        }
        unset($definition);
        $this->validated[$location] = $definitions;
    }

    public function record(string $location, ?int $customerId = null): void
    {
        if (!array_key_exists($location, $this->validated)) {
            $this->validate($location);
        }
        $definitions = $this->validated[$location];
        if ($definitions === []) {
            return;
        }
        $subjectKey = $customerId === null ? $this->resolveGuestSubjectKey() : null;
        $correlationId = $this->correlationIdProvider->get();
        $storeId = (int)$this->storeManager->getStore()->getId();
        foreach ($definitions as $definition) {
            $this->consentRecorder->record(
                (int)$definition['version_id'],
                (string)$definition['decision'],
                $location,
                $storeId,
                $customerId,
                $subjectKey,
                $correlationId
            );
        }
    }

    private function resolveGuestSubjectKey(): string
    {
        if ($this->guestSubjectKey !== null) {
            return $this->guestSubjectKey;
        }
        $existing = $this->cookieManager->getCookie(self::SUBJECT_COOKIE);
        if (is_string($existing)) {
            try {
                $this->subjectKeyGenerator->assertValid($existing);
                return $this->guestSubjectKey = $existing;
            } catch (\DomainException) {
                // Rotate malformed or legacy values without exposing them.
            }
        }
        $this->guestSubjectKey = $this->subjectKeyGenerator->generate();
        $metadata = $this->cookieMetadataFactory->create()
            ->setDuration(15552000)
            ->setPath('/')
            ->setHttpOnly(true)
            ->setSecure($this->request->isSecure())
            ->setSameSite('Lax');
        $this->cookieManager->setPublicCookie(self::SUBJECT_COOKIE, $this->guestSubjectKey, $metadata);
        return $this->guestSubjectKey;
    }
}
