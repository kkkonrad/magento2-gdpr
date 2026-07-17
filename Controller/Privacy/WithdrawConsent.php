<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Privacy;

use Kkkonrad\Gdpr\Application\Consent\ConsentWithdrawal;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;
use DomainException;
use Magento\Framework\Exception\LocalizedException;

class WithdrawConsent implements HttpPostActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly ConsentWithdrawal $consentWithdrawal,
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
            $this->consentWithdrawal->withdraw(
                (int)$this->customerSession->getCustomerId(),
                (int)$this->request->getParam('definition_id'),
                (int)$this->storeManager->getStore()->getId()
            );
            $this->messageManager->addSuccessMessage((string)__('Your consent has been withdrawn.'));
        } catch (LocalizedException|DomainException $exception) {
            $this->messageManager->addErrorMessage((string)__($exception->getMessage()));
        } catch (Throwable) {
            $this->messageManager->addErrorMessage(
                (string)__('The consent could not be withdrawn. Please try again later.')
            );
        }
        return $redirect;
    }
}
