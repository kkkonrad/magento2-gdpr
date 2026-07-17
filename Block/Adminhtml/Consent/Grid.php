<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Adminhtml\Consent;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\Template;

class Grid extends Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Template\Context $context,
        private readonly ResourceConnection $resourceConnection,
        private readonly RequestInterface $request,
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

    /** @return array<int, array<string, mixed>> */
    public function getEvents(): array
    {
        $event = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_event');
        $version = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_version');
        $definition = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition');
        $link = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_subject_link');
        $select = $this->resourceConnection->getConnection()->select()
            ->from(['event' => $event], [
                'event_id', 'customer_id', 'subject_key', 'decision', 'source', 'store_id', 'occurred_at',
            ])
            ->joinInner(['version' => $version], 'version.version_id = event.version_id', ['version'])
            ->joinInner(['definition' => $definition], 'definition.definition_id = version.definition_id', [
                'code', 'location', 'purpose',
            ])
            ->joinLeft(['subject_link' => $link], 'subject_link.subject_key = event.subject_key', [
                'effective_customer_id' => 'COALESCE(event.customer_id, subject_link.customer_id)',
            ]);
        foreach (['event_id', 'store_id'] as $field) {
            $value = $this->request->getParam($field);
            if ($value !== null && $value !== '') {
                $select->where('event.' . $field . ' = ?', (int)$value);
            }
        }
        $customerId = $this->request->getParam('customer_id');
        if ($customerId !== null && $customerId !== '') {
            $select->where('COALESCE(event.customer_id, subject_link.customer_id) = ?', (int)$customerId);
        }
        foreach (['decision', 'source'] as $field) {
            $value = trim((string)$this->request->getParam($field));
            if ($value !== '') {
                $select->where('event.' . $field . ' = ?', $value);
            }
        }
        $location = trim((string)$this->request->getParam('location'));
        if ($location !== '') {
            $select->where('definition.location = ?', $location);
        }
        $dateFrom = trim((string)$this->request->getParam('date_from'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
            $select->where('event.occurred_at >= ?', $dateFrom . ' 00:00:00');
        }
        $dateTo = trim((string)$this->request->getParam('date_to'));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
            $select->where('event.occurred_at <= ?', $dateTo . ' 23:59:59');
        }
        $rows = $this->resourceConnection->getConnection()->fetchAll(
            $select->order('event.event_id DESC')->limit(500)
        );
        foreach ($rows as &$row) {
            if ($row['subject_key'] !== null) {
                $row['subject_key'] = mb_substr((string)$row['subject_key'], 0, 8) . '…';
            }
        }
        unset($row);
        return $rows;
    }
}
