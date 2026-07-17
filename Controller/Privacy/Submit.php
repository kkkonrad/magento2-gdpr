<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Privacy;

use Kkkonrad\Gdpr\Application\DataRights\RequestSubmission;
use Kkkonrad\Gdpr\Api\DataRights\ReauthenticationInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use DomainException;
use Magento\Store\Model\StoreManagerInterface;
use Throwable;

class Submit implements HttpPostActionInterface
{
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly ReauthenticationInterface $reauthentication,
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
            $customerId = (int)$this->customerSession->getCustomerId();
            $password = $this->request->getParam('current_password');
            $type = (string)$this->request->getParam('type');
            if (in_array($type, ['anonymize', 'erase'], true)
                && (string)$this->request->getParam('confirm_irreversible') !== '1'
            ) {
                throw new \DomainException(
                    (string)__('You must confirm that you understand this operation is irreversible.')
                );
            }
            $this->reauthentication->reauthenticate(
                $customerId,
                (int)$this->storeManager->getStore()->getId(),
                is_string($password) ? $password : null
            );
            $this->requestSubmission->submit(
                $customerId,
                $type,
                (int)$this->storeManager->getStore()->getId()
            );
            $this->messageManager->addSuccessMessage((string)__('Your privacy request has been submitted.'));
        } catch (LocalizedException|DomainException $exception) {
            $this->messageManager->addErrorMessage((string)__($exception->getMessage()));
        } catch (Throwable) {
            $this->messageManager->addErrorMessage(
                (string)__('The privacy request could not be submitted. Please try again later.')
            );
        }

        return $redirect;
    }
}
