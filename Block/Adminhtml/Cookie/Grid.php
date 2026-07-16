<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Adminhtml\Cookie;

use Kkkonrad\Gdpr\Api\Cookie\CookieRegistryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;

class Grid extends Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Template\Context $context,
        private readonly CookieRegistryInterface $cookieRegistry,
        private readonly ResourceConnection $resourceConnection,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /** @return array<int, array<string, mixed>> */
    public function getGroups(): array
    {
        return $this->cookieRegistry->getGroups(0);
    }

    /** @return array<int, array<string, mixed>> */
    public function getRejected(): array
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_rejected_cookie');

        return $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()
                ->from($table, ['cookie_name', 'domain', 'is_unknown', 'occurrence_count', 'last_seen_at'])
                ->order('last_seen_at DESC')
                ->limit(100)
        );
    }
}
