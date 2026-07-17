<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Block\Adminhtml\Request;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;

class Details extends Template
{
    /** @param array<string, mixed> $data */
    public function __construct(
        Template\Context $context,
        private readonly ResourceConnection $resourceConnection,
        private readonly RequestInterface $request,
        private readonly AuthorizationInterface $authorization,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function canDecide(): bool
    {
        return $this->authorization->isAllowed('Kkkonrad_Gdpr::requests_decide');
    }

    public function canRetry(): bool
    {
        return $this->authorization->isAllowed('Kkkonrad_Gdpr::requests_retry');
    }

    /** @return array<string, mixed> */
    public function getGdprRequest(): array
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_request');
        $row = $this->resourceConnection->getConnection()->fetchRow(
            $this->resourceConnection->getConnection()->select()
                ->from($table, [
                    'request_id', 'public_id', 'customer_id', 'subject_key', 'type', 'status', 'store_id',
                    'public_reason', 'due_at', 'created_at', 'updated_at', 'completed_at',
                ])
                ->where('request_id = ?', (int)$this->request->getParam('id'))
        );
        if ($row === false) {
            throw NoSuchEntityException::singleField('request_id', (int)$this->request->getParam('id'));
        }
        if ($row['subject_key'] !== null) {
            $row['subject_key'] = mb_substr((string)$row['subject_key'], 0, 8) . '…';
        }
        return $row;
    }

    /** @return array<int, array<string, mixed>> */
    public function getTimeline(): array
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_request_event');
        return $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()
                ->from($table, [
                    'event_id', 'event_type', 'status_from', 'status_to', 'actor_type', 'actor_id',
                    'correlation_id', 'metadata_json', 'created_at',
                ])
                ->where('request_id = ?', (int)$this->request->getParam('id'))
                ->order('event_id ASC')
        );
    }

    /** @return array<int, array<string, mixed>> */
    public function getJobs(): array
    {
        $jobTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_job');
        return $this->resourceConnection->getConnection()->fetchAll(
            $this->resourceConnection->getConnection()->select()
                ->from($jobTable, [
                    'job_id', 'public_id', 'type', 'feature_code', 'status', 'checkpoint', 'attempt_count',
                    'error_code', 'created_at', 'updated_at', 'finished_at',
                ])
                ->where('request_id = ?', (int)$this->request->getParam('id'))
                ->order('job_id ASC')
        );
    }
}
