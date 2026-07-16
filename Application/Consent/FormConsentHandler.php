<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Consent;

use Kkkonrad\Gdpr\Api\Consent\ActiveConsentsProviderInterface;
use Kkkonrad\Gdpr\Api\Consent\ConsentRecorderInterface;
use Kkkonrad\Gdpr\Domain\Consent\ConsentDecision;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Kkkonrad\Gdpr\Domain\Consent\SubjectKeyGenerator;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\InputException;
use Magento\Store\Model\StoreManagerInterface;
use Ramsey\Uuid\Uuid;

class FormConsentHandler
{
    /** @var array<string, array<int, array<string, int|string|bool>>> */
    private array $validated = [];

    public function __construct(
        private readonly ActiveConsentsProviderInterface $activeConsentsProvider,
        private readonly ConsentRecorderInterface $consentRecorder,
        private readonly SubjectKeyGenerator $subjectKeyGenerator,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager
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
        $subjectKey = $customerId === null ? $this->subjectKeyGenerator->generate() : null;
        $correlationId = Uuid::uuid4()->toString();
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
}
