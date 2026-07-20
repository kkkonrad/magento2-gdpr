<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Adminhtml\Request;

use Kkkonrad\Gdpr\Application\DataRights\RequestDecision;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Throwable;

class Reject extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Kkkonrad_Gdpr::requests_decide';

    public function __construct(
        Context $context,
        private readonly RequestDecision $requestDecision,
        private readonly AdminSession $adminSession
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $this->requestDecision->reject(
                (int)$this->getRequest()->getParam('request_id'),
                (int)$this->adminSession->getUser()->getId(),
                (string)$this->getRequest()->getParam('public_reason'),
                (string)$this->getRequest()->getParam('admin_reason')
            );
            $this->messageManager->addSuccessMessage((string)__('The request was rejected.'));
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
