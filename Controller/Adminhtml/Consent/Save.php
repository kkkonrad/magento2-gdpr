<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Adminhtml\Consent;

use Kkkonrad\Gdpr\Api\Consent\ConsentDefinitionManagementInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Throwable;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Kkkonrad_Gdpr::consents_manage';

    public function __construct(
        Context $context,
        private readonly ConsentDefinitionManagementInterface $definitionManagement
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $definitionId = $this->getRequest()->getParam('definition_id');
            $this->definitionManagement->save(
                $definitionId !== null && $definitionId !== '' ? (int)$definitionId : null,
                (string)$this->getRequest()->getParam('code'),
                (string)$this->getRequest()->getParam('name'),
                (string)$this->getRequest()->getParam('location'),
                (string)$this->getRequest()->getParam('purpose'),
                (bool)$this->getRequest()->getParam('is_required'),
                (bool)$this->getRequest()->getParam('is_active'),
                (int)$this->getRequest()->getParam('sort_order'),
                (int)$this->getRequest()->getParam('store_id'),
                (string)$this->getRequest()->getParam('content'),
                (bool)$this->getRequest()->getParam('is_active_in_store')
            );
            $this->messageManager->addSuccessMessage((string)__('The consent draft was saved.'));
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
