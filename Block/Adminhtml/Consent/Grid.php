<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Adminhtml\Consent;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;

class Grid extends Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Template\Context $context,
        private readonly ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<int, array<string, mixed>> */
    public function getDefinitions(): array
    {
        $definition = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition');
        $store = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition_store');
        $version = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_version');

        return $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()
                ->from(['definition' => $definition])
                ->joinLeft(['store' => $store], 'store.definition_id = definition.definition_id', [
                    'store_id', 'content', 'store_active' => 'is_active',
                ])
                ->joinLeft(['version' => $version],
                    'version.definition_id = definition.definition_id AND version.store_id = store.store_id AND version.valid_to IS NULL',
                    ['published_version' => 'version'])
                ->order(['definition.sort_order ASC', 'definition.definition_id ASC', 'store.store_id ASC'])
        );
    }
}
