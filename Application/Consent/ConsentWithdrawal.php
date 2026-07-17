<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Consent;

use DomainException;
use Kkkonrad\Gdpr\Api\Consent\ConsentRecorderInterface;
use Kkkonrad\Gdpr\Api\CorrelationIdProviderInterface;
use Kkkonrad\Gdpr\Domain\Consent\ConsentDecision;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;

class ConsentWithdrawal
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ConsentRecorderInterface $consentRecorder,
        private readonly CorrelationIdProviderInterface $correlationIdProvider
    ) {
    }

    public function withdraw(int $customerId, int $definitionId, int $storeId): int
    {
        if ($customerId <= 0 || $definitionId <= 0) {
            throw new DomainException((string)__('A valid customer and consent definition are required.'));
        }
        $connection = $this->resourceConnection->getConnection();
        $versionTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_version');
        $eventTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_event');
        $linkTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_subject_link');
        $version = $connection->fetchRow(
            $connection->select()
                ->from($versionTable, ['version_id', 'content_snapshot'])
                ->where('definition_id = ?', $definitionId)
                ->where('store_id = ?', $storeId)
                ->order('version DESC')
                ->limit(1)
        );
        if ($version === false && $storeId !== 0) {
            $version = $connection->fetchRow(
                $connection->select()
                    ->from($versionTable, ['version_id', 'content_snapshot'])
                    ->where('definition_id = ?', $definitionId)
                    ->where('store_id = ?', 0)
                    ->order('version DESC')
                    ->limit(1)
            );
        }
        if ($version === false) {
            throw NoSuchEntityException::singleField('definition_id', $definitionId);
        }
        $snapshot = json_decode((string)$version['content_snapshot'], true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot) || (bool)($snapshot['is_required'] ?? true)) {
            throw new DomainException(
                (string)__('A required consent cannot be withdrawn from the privacy dashboard.')
            );
        }
        $latestDecision = (string)$connection->fetchOne(
            $connection->select()
                ->from(['event' => $eventTable], ['decision'])
                ->joinInner(
                    ['version' => $versionTable],
                    'version.version_id = event.version_id',
                    []
                )
                ->joinLeft(['subject_link' => $linkTable], 'subject_link.subject_key = event.subject_key', [])
                ->where('COALESCE(event.customer_id, subject_link.customer_id) = ?', $customerId)
                ->where('version.definition_id = ?', $definitionId)
                ->order('event.event_id DESC')
                ->limit(1)
        );
        if ($latestDecision !== ConsentDecision::ACCEPTED) {
            throw new DomainException((string)__('This consent is not currently granted.'));
        }

        return $this->consentRecorder->record(
            (int)$version['version_id'],
            ConsentDecision::DECLINED,
            'customer_account',
            $storeId,
            $customerId,
            null,
            $this->correlationIdProvider->get()
        );
    }
}
