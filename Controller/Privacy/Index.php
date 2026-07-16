<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Privacy;

use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\Shared\Feature\FeatureCode;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;
use Magento\Store\Model\StoreManagerInterface;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly FeatureManagerInterface $featureManager,
        private readonly StoreManagerInterface $storeManager,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory
    ) {
    }

    public function execute()
    {
        if (!$this->customerSession->authenticate()) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }
        if (!$this->featureManager->isEnabled(
            FeatureCode::DASHBOARD,
            (int)$this->storeManager->getStore()->getId()
        )) {
            return $this->redirectFactory->create()->setPath('customer/account');
        }
        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set((string)__('Privacy and personal data'));

        return $page;
    }
}
