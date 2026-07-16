<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Observer\Consent;

use Kkkonrad\Gdpr\Application\Consent\FormConsentHandler;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class RecordRegistrationConsent implements ObserverInterface
{
    public function __construct(private readonly FormConsentHandler $formConsentHandler)
    {
    }

    public function execute(Observer $observer): void
    {
        $customer = $observer->getData('customer');
        if ($customer instanceof CustomerInterface && $customer->getId() !== null) {
            $this->formConsentHandler->record(ConsentLocation::REGISTRATION, (int)$customer->getId());
        }
    }
}
