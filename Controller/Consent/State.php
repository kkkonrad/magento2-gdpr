<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Consent;

use Kkkonrad\Gdpr\Api\Cookie\CookiePolicyVersionProviderInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Application\Cookie\CookieDecisionStateProvider;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;

class State implements HttpGetActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly FeatureManagerInterface $featureManager,
        private readonly CookiePolicyVersionProviderInterface $policyVersionProvider,
        private readonly CookieDecisionStateProvider $decisionStateProvider
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'private, no-store, max-age=0', true);
        $result->setHeader('Pragma', 'no-cache', true);
        $result->setHeader('X-Content-Type-Options', 'nosniff', true);

        $storeId = (int)$this->storeManager->getStore()->getId();
        if (!$this->featureManager->isEnabled(FeatureCode::COOKIE, $storeId)) {
            return $result->setHttpResponseCode(404)->setData(['decision' => null]);
        }

        $policy = $this->policyVersionProvider->getOrPublishCurrent($storeId);

        return $result->setData([
            'decision' => $this->decisionStateProvider->getVerifiedDecision(
                $storeId,
                $policy['public_id']
            ),
        ]);
    }
}
