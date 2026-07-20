<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Plugin\Consent;

use Kkkonrad\Gdpr\Application\Consent\FormConsentHandler;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Magento\Checkout\Api\PaymentInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\AddressInterface;
use Magento\Quote\Api\Data\PaymentInterface;

class CheckoutConsentPlugin
{
    public function __construct(
        private readonly FormConsentHandler $formConsentHandler,
        private readonly CartRepositoryInterface $cartRepository
    ) {
    }

    public function aroundSavePaymentInformationAndPlaceOrder(
        PaymentInformationManagementInterface $subject,
        callable $proceed,
        int $cartId,
        PaymentInterface $paymentMethod,
        ?AddressInterface $billingAddress = null
    ): int {
        $this->formConsentHandler->validateSubmitted(
            ConsentLocation::CHECKOUT,
            $this->extract($paymentMethod)
        );
        $quoteCustomerId = $this->cartRepository->getActive($cartId)->getCustomer()->getId();
        $customerId = $quoteCustomerId !== null && (int)$quoteCustomerId > 0
            ? (int)$quoteCustomerId
            : null;
        $this->formConsentHandler->record(ConsentLocation::CHECKOUT, $customerId);

        return (int)$proceed($cartId, $paymentMethod, $billingAddress);
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
