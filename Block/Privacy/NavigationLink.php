<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Privacy;

use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Customer\Block\Account\SortLink;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

class NavigationLink extends SortLink
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        private readonly FeatureManagerInterface $featureManager,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->featureManager->isEnabled(
            FeatureCode::DASHBOARD,
            (int)$this->storeManager->getStore()->getId()
        )) {
            return '';
        }

        return parent::_toHtml();
    }
}
