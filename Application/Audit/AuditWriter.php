<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Audit;

use Kkkonrad\Gdpr\Domain\Shared\Audit\SensitiveDataRedactor;
use Magento\Framework\App\ResourceConnection;

class AuditWriter
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly SensitiveDataRedactor $redactor
    ) {
    }

    /** @param array<string, mixed> $metadata */
    public function write(
        string $eventType,
        string $entityType,
        ?string $entityId = null,
        string $actorType = 'system',
        ?int $actorId = null,
        ?int $storeId = null,
        ?string $correlationId = null,
        array $metadata = []
    ): void {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_audit_event');
        $this->resourceConnection->getConnection()->insert($table, [
            'event_type' => mb_substr($eventType, 0, 64),
            'entity_type' => mb_substr($entityType, 0, 64),
            'entity_id' => $entityId !== null ? mb_substr($entityId, 0, 64) : null,
            'actor_type' => mb_substr($actorType, 0, 16),
            'actor_id' => $actorId,
            'store_id' => $storeId,
            'correlation_id' => $correlationId !== null ? mb_substr($correlationId, 0, 64) : null,
            'metadata_json' => $metadata !== []
                ? json_encode($this->redactor->redact($metadata), JSON_THROW_ON_ERROR)
                : null,
        ]);
    }
}
