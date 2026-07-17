<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Observer\Consent;

use Kkkonrad\Gdpr\Api\ConfigProviderInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Application\Consent\ConsentSubjectLinker;
use Kkkonrad\Gdpr\Application\Consent\FormConsentHandler;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class LinkGuestConsentsOnLogin implements ObserverInterface
{
    public function __construct(
        private readonly ConsentSubjectLinker $subjectLinker,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly FeatureManagerInterface $featureManager,
        private readonly ConfigProviderInterface $configProvider
    ) {
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getData('customer');
        $customerId = is_object($customer) && method_exists($customer, 'getId') ? (int)$customer->getId() : 0;
        $storeId = (int)$this->storeManager->getStore()->getId();
        $subjectKey = $this->cookieManager->getCookie(FormConsentHandler::SUBJECT_COOKIE);
        if ($customerId <= 0 || !is_string($subjectKey)
            || !$this->featureManager->isEnabled(FeatureCode::CONSENT, $storeId)
            || $this->configProvider->getString(
                'kkkonrad_gdpr/consent/link_guest_consents_on_login',
                $storeId
            ) !== '1'
        ) {
            return;
        }
        try {
            $this->subjectLinker->link($subjectKey, $customerId, $storeId);
        } catch (Throwable) {
            // A stale/cross-account pseudonym is discarded and never reassigned.
        }
        $this->cookieManager->deleteCookie(
            FormConsentHandler::SUBJECT_COOKIE,
            $this->cookieMetadataFactory->createCookieMetadata()->setPath('/')
        );
    }
}
