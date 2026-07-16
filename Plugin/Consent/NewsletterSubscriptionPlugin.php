<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Plugin\Consent;

use Kkkonrad\Gdpr\Application\Consent\FormConsentHandler;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http;
use Magento\Newsletter\Model\Subscriber;
use Magento\Newsletter\Model\SubscriptionManagerInterface;

class NewsletterSubscriptionPlugin
{
    public function __construct(
        private readonly FormConsentHandler $formConsentHandler,
        private readonly Http $request,
        private readonly CustomerSession $customerSession
    ) {
    }

    public function aroundSubscribe(
        SubscriptionManagerInterface $subject,
        callable $proceed,
        string $email,
        int $storeId
    ): Subscriber {
        if ($this->request->getFullActionName() !== 'newsletter_subscriber_new') {
            return $proceed($email, $storeId);
        }
        $this->formConsentHandler->validate(ConsentLocation::NEWSLETTER);
        $subscriber = $proceed($email, $storeId);
        $customerId = $this->customerSession->isLoggedIn()
            ? (int)$this->customerSession->getCustomerId()
            : null;
        $this->formConsentHandler->record(ConsentLocation::NEWSLETTER, $customerId);

        return $subscriber;
    }
}
