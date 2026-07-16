<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Privacy;

use Kkkonrad\Gdpr\Application\DataRights\RequestSubmission;
use Magento\Customer\Api\AccountManagementInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class Submit implements HttpPostActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly AccountManagementInterface $accountManagement,
        private readonly RequestSubmission $requestSubmission,
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager
    ) {
    }

    public function execute(): Redirect
    {
        $redirect = $this->redirectFactory->create()->setPath('gdpr/privacy/index');
        if (!$this->customerSession->isLoggedIn()) {
            return $redirect->setPath('customer/account/login');
        }
        try {
            $password = (string)$this->request->getParam('current_password');
            if ($password === '') {
                throw new \DomainException('Current password is required.');
            }
            $this->accountManagement->authenticate(
                (string)$this->customerSession->getCustomer()->getEmail(),
                $password
            );
            $this->requestSubmission->submit(
                (int)$this->customerSession->getCustomerId(),
                (string)$this->request->getParam('type'),
                (int)$this->storeManager->getStore()->getId()
            );
            $this->messageManager->addSuccessMessage((string)__('Your privacy request has been submitted.'));
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $redirect;
    }
}
