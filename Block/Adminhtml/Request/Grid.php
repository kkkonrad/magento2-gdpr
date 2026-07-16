<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Adminhtml\Request;

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
    public function getRequests(): array
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');

        return $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()
                ->from($table, [
                    'request_id', 'public_id', 'customer_id', 'type', 'status', 'store_id',
                    'public_reason', 'created_at', 'updated_at',
                ])
                ->order('created_at DESC')
                ->limit(200)
        );
    }
}
