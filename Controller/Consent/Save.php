<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Consent;

use DomainException;
use Kkkonrad\Gdpr\Api\Cookie\CookieDecisionRecorderInterface;
use Kkkonrad\Gdpr\Domain\Cookie\DecisionToken;
use Kkkonrad\Gdpr\Api\Geo\RegionResolverInterface;
use Kkkonrad\Gdpr\Application\Cookie\CookieDecisionStateProvider;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\PublicCookieMetadataFactory;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class Save implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly Http $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CookieDecisionRecorderInterface $decisionRecorder,
        private readonly DecisionToken $decisionToken,
        private readonly CookieManagerInterface $cookieManager,
        private readonly PublicCookieMetadataFactory $cookieMetadataFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerSession $customerSession,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly RegionResolverInterface $regionResolver
    ) {
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        $this->request->setParam('form_key', (string)$this->request->getHeader('X-Form-Key'));

        return $this->formKeyValidator->validate($request);
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        try {
            $input = json_decode((string)$this->request->getContent(), true, 8, JSON_THROW_ON_ERROR);
            if (!is_array($input) || !is_array($input['choices'] ?? null)) {
                throw new DomainException('Cookie choices are required.');
            }
            $subjectKey = $this->resolveSubjectKey();
            $record = $this->decisionRecorder->record(
                (int)$this->storeManager->getStore()->getId(),
                $input['choices'],
                $subjectKey,
                $this->customerSession->isLoggedIn() ? (int)$this->customerSession->getCustomerId() : null,
                $this->regionResolver->resolve()['region'],
                isset($input['correlation_id']) ? (string)$input['correlation_id'] : null
            );
            $metadata = $this->cookieMetadataFactory->create()
                ->setDuration(max(1, $record['expires_at'] - time()))
                ->setPath('/')
                ->setHttpOnly(false)
                ->setSecure($this->request->isSecure())
                ->setSameSite('Lax');
            $this->cookieManager->setPublicCookie(
                CookieDecisionStateProvider::COOKIE_NAME,
                $record['token'],
                $metadata
            );

            return $result->setData([
                'success' => true,
                'choices' => $record['choices'],
            ]);
        } catch (DomainException $exception) {
            return $result->setHttpResponseCode(400)->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            return $result->setHttpResponseCode(500)->setData([
                'success' => false,
                'message' => (string)__('The cookie preference could not be saved. Please try again.'),
            ]);
        }
    }

    private function resolveSubjectKey(): ?string
    {
        $token = $this->cookieManager->getCookie(CookieDecisionStateProvider::COOKIE_NAME);
        if ($token === null) {
            return null;
        }
        try {
            $payload = $this->decisionToken->verify($token);
            $subjectKey = $payload['subject_key'] ?? null;

            return is_string($subjectKey) ? $subjectKey : null;
        } catch (DomainException) {
            return null;
        }
    }
}
