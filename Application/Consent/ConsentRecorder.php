<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Consent;

use DomainException;
use Kkkonrad\Gdpr\Api\Consent\ConsentRecorderInterface;
use Kkkonrad\Gdpr\Api\FeatureManagerInterface;
use Kkkonrad\Gdpr\Domain\Consent\ConsentDecision;
use Kkkonrad\Gdpr\Domain\Consent\ConsentFeatureResolver;
use Kkkonrad\Gdpr\Domain\Consent\SubjectKeyGenerator;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;

class ConsentRecorder implements ConsentRecorderInterface
{
    private const SOURCES = ['registration', 'newsletter', 'contact', 'checkout', 'customer_account', 'admin'];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly FeatureManagerInterface $featureManager,
        private readonly ConsentFeatureResolver $featureResolver,
        private readonly SubjectKeyGenerator $subjectKeyGenerator
    ) {
    }

    public function record(
        int $versionId,
        string $decision,
        string $source,
        int $storeId,
        ?int $customerId = null,
        ?string $subjectKey = null,
        ?string $correlationId = null
    ): int {
        ConsentDecision::assertValid($decision);
        if (!in_array($source, self::SOURCES, true)) {
            throw new DomainException(sprintf('Unsupported consent source "%s".', $source));
        }
        if ($customerId === null && $subjectKey === null) {
            $subjectKey = $this->subjectKeyGenerator->generate();
        }
        if ($subjectKey !== null) {
            $this->subjectKeyGenerator->assertValid($subjectKey);
        }

        $versionTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_version');
        $definitionTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition');
        $eventTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_event');
        $connection = $this->resourceConnection->getConnection();
        $version = $connection->fetchRow(
            $connection->select()
                ->from(['version' => $versionTable], ['version_id', 'store_id', 'content_snapshot'])
                ->joinInner(
                    ['definition' => $definitionTable],
                    'definition.definition_id = version.definition_id',
                    []
                )
                ->where('version.version_id = ?', $versionId)
        );
        if ($version === false) {
            throw NoSuchEntityException::singleField('version_id', $versionId);
        }
        if (!in_array((int)$version['store_id'], [$storeId, 0], true)) {
            throw new DomainException('Consent version does not belong to the current store scope.');
        }
        $snapshot = json_decode((string)$version['content_snapshot'], true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($snapshot)) {
            throw new DomainException('Consent version snapshot is invalid.');
        }
        $location = (string)($snapshot['location'] ?? '');
        if (!$this->featureManager->isEnabled($this->featureResolver->forLocation($location), $storeId)) {
            throw new DomainException('Consent recording is disabled for this location.');
        }
        if ((bool)($snapshot['is_required'] ?? false) && $decision !== ConsentDecision::ACCEPTED) {
            throw new DomainException('A required consent cannot be declined for this operation.');
        }

        $connection->insert($eventTable, [
            'version_id' => $versionId,
            'customer_id' => $customerId,
            'subject_key' => $subjectKey,
            'decision' => $decision,
            'source' => $source,
            'store_id' => $storeId,
            'correlation_id' => $correlationId !== null ? mb_substr($correlationId, 0, 64) : null,
        ]);

        return (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
    }
}
