<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Controller\Adminhtml\Consent;

use Kkkonrad\Gdpr\Api\Consent\ConsentDefinitionManagementInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Throwable;

class Archive extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Kkkonrad_Gdpr::consents_manage';

    public function __construct(
        Context $context,
        private readonly ResourceConnection $resourceConnection,
        private readonly ConsentDefinitionManagementInterface $definitionManagement
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $definitionId = (int)$this->getRequest()->getParam('definition_id');
            $storeId = (int)$this->getRequest()->getParam('store_id');
            $definitionTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition');
            $storeTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition_store');
            $row = $this->resourceConnection->getConnection()->fetchRow(
                $this->resourceConnection->getConnection()->select()
                    ->from(['definition' => $definitionTable])
                    ->joinInner(['store' => $storeTable], 'store.definition_id = definition.definition_id', ['content'])
                    ->where('definition.definition_id = ?', $definitionId)
                    ->where('store.store_id = ?', $storeId)
            );
            if ($row === false) {
                throw new \DomainException('The consent draft no longer exists.');
            }
            $this->definitionManagement->save(
                $definitionId,
                (string)$row['code'],
                (string)$row['name'],
                (string)$row['location'],
                (string)$row['purpose'],
                (bool)$row['is_required'],
                false,
                (int)$row['sort_order'],
                $storeId,
                (string)$row['content'],
                false
            );
            $this->messageManager->addSuccessMessage((string)__('The consent definition was archived.'));
        } catch (Throwable $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        }
        return $this->resultRedirectFactory->create()->setPath('*/*/index');
    }
}
