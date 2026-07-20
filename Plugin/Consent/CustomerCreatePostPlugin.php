<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Plugin\Consent;

use Kkkonrad\Gdpr\Application\Consent\FormConsentHandler;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\Request\Http;

class CustomerCreatePostPlugin
{
    public function __construct(
        private readonly FormConsentHandler $formConsentHandler,
        private readonly Http $request
    ) {
    }

    /**
     * @return array{CustomerInterface, string|null, string|null}
     */
    public function beforeCreateAccount(
        AccountManagementInterface $subject,
        CustomerInterface $customer,
        ?string $password = null,
        ?string $redirectUrl = null
    ): array {
        if ($this->request->getFullActionName() === 'customer_account_createpost') {
            $this->formConsentHandler->validate(ConsentLocation::REGISTRATION);
        }

        return [$customer, $password, $redirectUrl];
    }
}
