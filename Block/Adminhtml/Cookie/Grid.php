<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Adminhtml\Cookie;

use Kkkonrad\Gdpr\Api\Cookie\CookieRegistryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Template;

class Grid extends Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Template\Context $context,
        private readonly CookieRegistryInterface $cookieRegistry,
        private readonly ResourceConnection $resourceConnection,
        private readonly RequestInterface $request,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<int, array<string, mixed>> */
    public function getGroups(): array
    {
        return $this->cookieRegistry->getGroups($this->getStoreId());
    }

    public function getStoreId(): int
    {
        return max(0, (int)$this->request->getParam('store_id', 0));
    }

    /** @return array<int, array<string, mixed>> */
    public function getRejected(): array
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_rejected_cookie');

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from($table, ['rejected_id', 'cookie_name', 'domain', 'is_unknown', 'occurrence_count', 'last_seen_at']);
        $name = trim((string)$this->request->getParam('rejected_name'));
        $domain = trim((string)$this->request->getParam('rejected_domain'));
        $unknown = (string)$this->request->getParam('rejected_unknown');
        if ($name !== '') {
            $select->where('cookie_name LIKE ?', '%' . $name . '%');
        }
        if ($domain !== '') {
            $select->where('domain LIKE ?', '%' . $domain . '%');
        }
        if (in_array($unknown, ['0', '1'], true)) {
            $select->where('is_unknown = ?', (int)$unknown);
        }
        return $connection->fetchAll($select->order('last_seen_at DESC')->limit(100));
    }
}
