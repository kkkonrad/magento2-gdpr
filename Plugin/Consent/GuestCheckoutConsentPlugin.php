<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Plugin\Consent;

use Kkkonrad\Gdpr\Application\Consent\FormConsentHandler;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Magento\Checkout\Api\GuestPaymentInformationManagementInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;

class GuestCheckoutConsentPlugin
{
    public function __construct(private readonly FormConsentHandler $formConsentHandler)
    {
    }

    public function aroundSavePaymentInformationAndPlaceOrder(
        GuestPaymentInformationManagementInterface $subject,
        callable $proceed,
        string $cartId,
        string $email,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): int {
        $this->formConsentHandler->validateSubmitted(
            ConsentLocation::CHECKOUT,
            $this->extract($paymentMethod)
        );
        $this->formConsentHandler->record(ConsentLocation::CHECKOUT);

        return (int)$proceed($cartId, $email, $paymentMethod, $billingAddress);
    }

    /** @return array<int|string, mixed> */
    private function extract(PaymentInterface $paymentMethod): array
    {
        $extensionAttributes = $paymentMethod->getExtensionAttributes();
        $raw = $extensionAttributes !== null
            ? $extensionAttributes->getKkkonradGdprConsents()
            : null;
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
