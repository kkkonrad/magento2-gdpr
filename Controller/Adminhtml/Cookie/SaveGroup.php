<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Adminhtml\Cookie;

use Kkkonrad\Gdpr\Application\Cookie\CookieCatalogManagement;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Throwable;

class SaveGroup extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Kkkonrad_Gdpr::cookies_manage';

    public function __construct(Context $context, private readonly CookieCatalogManagement $catalogManagement)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $id = $this->getRequest()->getParam('group_id');
            $this->catalogManagement->saveGroup(
                $id !== null && $id !== '' ? (int)$id : null,
                (string)$this->getRequest()->getParam('code'),
                (string)$this->getRequest()->getParam('type'),
                (bool)$this->getRequest()->getParam('is_required'),
                (bool)$this->getRequest()->getParam('is_active'),
                (int)$this->getRequest()->getParam('priority'),
                (int)$this->getRequest()->getParam('store_id'),
                (string)$this->getRequest()->getParam('name'),
                (string)$this->getRequest()->getParam('description')
            );
            $this->messageManager->addSuccessMessage((string)__('The cookie group was saved.'));
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
