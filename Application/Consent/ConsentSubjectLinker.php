<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Consent;

use DomainException;
use Kkkonrad\Gdpr\Api\CorrelationIdProviderInterface;
use Kkkonrad\Gdpr\Domain\Consent\SubjectKeyGenerator;
use Magento\Framework\App\ResourceConnection;

class ConsentSubjectLinker
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly SubjectKeyGenerator $subjectKeyGenerator,
        private readonly CorrelationIdProviderInterface $correlationIdProvider
    ) {
    }

    public function link(string $subjectKey, int $customerId, int $storeId): void
    {
        $this->subjectKeyGenerator->assertValid($subjectKey);
        if ($customerId <= 0) {
            throw new DomainException('A valid customer is required to link guest consent evidence.');
        }
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_subject_link');
        $connection = $this->resourceConnection->getConnection();
        $existing = $connection->fetchRow(
            $connection->select()->from($table, ['customer_id'])->where('subject_key = ?', $subjectKey)
        );
        if ($existing !== false) {
            if ((int)$existing['customer_id'] !== $customerId) {
                throw new DomainException('This guest consent subject is already linked to another account.');
            }
            return;
        }
        $connection->insert($table, [
            'subject_key' => $subjectKey,
            'customer_id' => $customerId,
            'store_id' => $storeId,
            'correlation_id' => $this->correlationIdProvider->get(),
        ]);
    }
}
