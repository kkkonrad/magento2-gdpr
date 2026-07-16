<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Model\Checkout;

use Kkkonrad\Gdpr\Api\Consent\ActiveConsentsProviderInterface;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;

class ConfigProvider implements ConfigProviderInterface
{
    public function __construct(
        private readonly ActiveConsentsProviderInterface $activeConsentsProvider,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /** @return array<string, mixed> */
    public function getConfig(): array
    {
        $consents = $this->activeConsentsProvider->getForLocation(
            ConsentLocation::CHECKOUT,
            (int)$this->storeManager->getStore()->getId()
        );

        return [
            'kkkonradGdpr' => [
                'consents' => $consents,
                'requiredMessage' => (string)__('Please accept all required privacy consents.'),
            ],
        ];
    }
}
