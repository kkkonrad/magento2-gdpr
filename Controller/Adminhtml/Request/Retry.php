<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Adminhtml\Request;

use Kkkonrad\Gdpr\Application\DataRights\RequestDecision;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Throwable;

class Retry extends Action
{
    public const ADMIN_RESOURCE = 'Kkkonrad_Gdpr::requests_retry';

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
            $this->requestDecision->retry(
                (int)$this->getRequest()->getParam('request_id'),
                (int)$this->adminSession->getUser()->getId(),
                (string)$this->getRequest()->getParam('admin_reason')
            );
            $this->messageManager->addSuccessMessage((string)__('The request was queued for retry.'));
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
