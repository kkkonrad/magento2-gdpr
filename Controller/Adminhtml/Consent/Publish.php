<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Adminhtml\Consent;

use Kkkonrad\Gdpr\Api\Consent\ConsentDefinitionManagementInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Throwable;

class Publish extends Action
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
            $this->definitionManagement->publish(
                (int)$this->getRequest()->getParam('definition_id'),
                (int)$this->getRequest()->getParam('store_id')
            );
            $this->messageManager->addSuccessMessage((string)__('A new immutable consent version was published.'));
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
