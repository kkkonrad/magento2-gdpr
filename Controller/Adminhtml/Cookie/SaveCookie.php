<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Adminhtml\Cookie;

use Kkkonrad\Gdpr\Application\Cookie\CookieCatalogManagement;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Throwable;

class SaveCookie extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Kkkonrad_Gdpr::cookies_manage';

    public function __construct(Context $context, private readonly CookieCatalogManagement $catalogManagement)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $id = $this->getRequest()->getParam('cookie_id');
            $lifetime = $this->getRequest()->getParam('lifetime');
            $this->catalogManagement->saveCookie(
                $id !== null && $id !== '' ? (int)$id : null,
                (int)$this->getRequest()->getParam('group_id'),
                (string)$this->getRequest()->getParam('name'),
                (string)$this->getRequest()->getParam('code_pattern'),
                (string)$this->getRequest()->getParam('storage_type'),
                $lifetime !== null && $lifetime !== '' ? (int)$lifetime : null,
                (bool)$this->getRequest()->getParam('is_active'),
                (int)$this->getRequest()->getParam('store_id'),
                (string)$this->getRequest()->getParam('description'),
                (bool)$this->getRequest()->getParam('is_active_in_store')
            );
            $this->messageManager->addSuccessMessage((string)__('The cookie definition was saved.'));
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index', [
            'store_id' => max(0, (int)$this->getRequest()->getParam('store_id')),
        ]);
    }
}
