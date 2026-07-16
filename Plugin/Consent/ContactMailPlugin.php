<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Plugin\Consent;

use Kkkonrad\Gdpr\Application\Consent\FormConsentHandler;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Request\Http;
use Magento\Contact\Model\MailInterface;

class ContactMailPlugin
{
    public function __construct(
        private readonly FormConsentHandler $formConsentHandler,
        private readonly Http $request,
        private readonly CustomerSession $customerSession
    ) {
    }

    /** @param array<string, mixed> $variables */
    public function aroundSend(
        MailInterface $subject,
        callable $proceed,
        string $replyTo,
        array $variables
    ): void {
        if ($this->request->getFullActionName() !== 'contact_index_post') {
            $proceed($replyTo, $variables);
            return;
        }
        $this->formConsentHandler->validate(ConsentLocation::CONTACT);
        $proceed($replyTo, $variables);
        $customerId = $this->customerSession->isLoggedIn()
            ? (int)$this->customerSession->getCustomerId()
            : null;
        $this->formConsentHandler->record(ConsentLocation::CONTACT, $customerId);
    }
}
