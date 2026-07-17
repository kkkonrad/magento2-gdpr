<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Adminhtml\Request;

use Kkkonrad\Gdpr\Application\DataRights\AdminExportSubmission;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Throwable;

class CreateExport extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Kkkonrad_Gdpr::requests_export_on_behalf';

    public function __construct(
        Context $context,
        private readonly AdminExportSubmission $adminExportSubmission,
        private readonly AdminSession $adminSession
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $user = $this->adminSession->getUser();
            $this->adminExportSubmission->submit(
                (int)$this->getRequest()->getParam('customer_id'),
                $user !== null ? (int)$user->getId() : 0,
                (string)$this->getRequest()->getParam('admin_reason')
            );
            $this->messageManager->addSuccessMessage((string)__('The customer data export was queued.'));
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
