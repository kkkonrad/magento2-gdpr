<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Consent;

use Kkkonrad\Gdpr\Api\Consent\ActiveConsentsProviderInterface;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class FormConfig extends Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Template\Context $context,
        private readonly ActiveConsentsProviderInterface $activeConsentsProvider,
        private readonly StoreManagerInterface $storeManager,
        private readonly Json $json,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getJsonConfig(): string
    {
        $storeId = (int)$this->storeManager->getStore()->getId();
        $locations = [];
        foreach (ConsentLocation::ALL as $location) {
            $definitions = $this->activeConsentsProvider->getForLocation($location, $storeId);
            if ($definitions !== []) {
                $locations[$location] = $definitions;
            }
        }

        return $this->json->serialize([
            'locations' => $locations,
            'requiredMessage' => (string)__('This consent is required.'),
        ]);
    }
}
