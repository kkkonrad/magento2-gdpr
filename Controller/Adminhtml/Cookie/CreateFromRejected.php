<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Adminhtml\Cookie;

use Kkkonrad\Gdpr\Application\Cookie\CookieCatalogManagement;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class CreateFromRejected extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Kkkonrad_Gdpr::cookies_manage';

    public function __construct(
        Context $context,
        private readonly ResourceConnection $resourceConnection,
        private readonly CookieCatalogManagement $catalogManagement
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $storeId = max(0, (int)$this->getRequest()->getParam('store_id'));
        try {
            $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_rejected_cookie');
            $row = $this->resourceConnection->getConnection()->fetchRow(
                $this->resourceConnection->getConnection()->select()
                    ->from($table, ['cookie_name', 'store_id'])
                    ->where('rejected_id = ?', (int)$this->getRequest()->getParam('rejected_id'))
            );
            if ($row === false) {
                throw new \DomainException('The rejected cookie diagnostic no longer exists.');
            }
            $storeId = max(0, (int)$row['store_id']);
            $cookieTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_cookie');
            $existing = $this->resourceConnection->getConnection()->fetchRow(
                $this->resourceConnection->getConnection()->select()
                    ->from($cookieTable, ['cookie_id'])
                    ->where('storage_type = ?', 'cookie')
                    ->where('code_pattern = ?', (string)$row['cookie_name'])
                    ->limit(1)
            );
            if (is_array($existing)) {
                $this->messageManager->addSuccessMessage(
                    (string)__('An inactive cookie draft already exists for this diagnostic.')
                );
                return $this->resultRedirectFactory->create()->setPath('*/*/index', [
                    'store_id' => $storeId,
                ]);
            }
            $this->catalogManagement->saveCookie(
                null,
                (int)$this->getRequest()->getParam('group_id'),
                (string)$row['cookie_name'],
                (string)$row['cookie_name'],
                'cookie',
                null,
                false,
                (int)$row['store_id'],
                (string)__('Created as an inactive draft from rejected-cookie diagnostics.'),
                false
            );
            $this->messageManager->addSuccessMessage((string)__('An inactive cookie draft was created.'));
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index', [
            'store_id' => $storeId,
        ]);
    }
}
