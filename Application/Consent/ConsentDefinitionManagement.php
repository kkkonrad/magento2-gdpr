<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\Consent;

use DomainException;
use Kkkonrad\Gdpr\Api\Consent\ConsentDefinitionManagementInterface;
use Kkkonrad\Gdpr\Domain\Consent\ConsentContentSanitizer;
use Kkkonrad\Gdpr\Domain\Consent\ConsentLocation;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Throwable;

class ConsentDefinitionManagement implements ConsentDefinitionManagementInterface
{
    private const CODE_PATTERN = '/^[a-z][a-z0-9_.-]{2,63}$/';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ConsentContentSanitizer $contentSanitizer
    ) {
    }

    public function save(
        ?int $definitionId,
        string $code,
        string $name,
        string $location,
        string $purpose,
        bool $isRequired,
        bool $isActive,
        int $sortOrder,
        int $storeId,
        string $content,
        bool $isActiveInStore = true
    ): int {
        $this->validate($code, $name, $location, $purpose, $storeId, $content);
        $sanitizedContent = $this->contentSanitizer->sanitize($content);
        if ($sanitizedContent === '') {
            throw new DomainException('Consent content cannot be empty after sanitization.');
        }

        $connection = $this->resourceConnection->getConnection();
        $definitionTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition');
        $storeTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition_store');
        $connection->beginTransaction();
        try {
            $definitionData = [
                'code' => $code,
                'name' => trim($name),
                'location' => $location,
                'purpose' => trim($purpose),
                'is_required' => (int)$isRequired,
                'is_active' => (int)$isActive,
                'sort_order' => max(0, $sortOrder),
            ];
            if ($definitionId === null) {
                $connection->insert($definitionTable, $definitionData);
                $definitionId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
            } else {
                $affected = $connection->update(
                    $definitionTable,
                    $definitionData,
                    ['definition_id = ?' => $definitionId]
                );
                if ($affected === 0 && !$this->definitionExists($definitionId)) {
                    throw NoSuchEntityException::singleField('definition_id', $definitionId);
                }
            }

            $connection->insertOnDuplicate($storeTable, [
                'definition_id' => $definitionId,
                'store_id' => $storeId,
                'content' => $sanitizedContent,
                'is_active' => (int)$isActiveInStore,
            ], ['content', 'is_active']);
            $connection->commit();

            return $definitionId;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    public function publish(int $definitionId, int $storeId): int
    {
        $connection = $this->resourceConnection->getConnection();
        $definitionTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition');
        $storeTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition_store');
        $versionTable = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_version');
        $connection->beginTransaction();
        try {
            $definition = $connection->fetchRow(
                $connection->select()
                    ->from(['definition' => $definitionTable])
                    ->joinInner(
                        ['store' => $storeTable],
                        'store.definition_id = definition.definition_id',
                        ['content', 'store_active' => 'is_active']
                    )
                    ->where('definition.definition_id = ?', $definitionId)
                    ->where('store.store_id = ?', $storeId)
                    ->forUpdate(true)
            );
            if ($definition === false) {
                throw new DomainException('A store-view consent draft must exist before publication.');
            }

            $currentVersion = (int)$connection->fetchOne(
                $connection->select()
                    ->from($versionTable, ['MAX(version)'])
                    ->where('definition_id = ?', $definitionId)
                    ->where('store_id = ?', $storeId)
                    ->forUpdate(true)
            );
            $snapshot = json_encode([
                'code' => (string)$definition['code'],
                'location' => (string)$definition['location'],
                'purpose' => (string)$definition['purpose'],
                'is_required' => (bool)$definition['is_required'],
                'content' => (string)$definition['content'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $now = gmdate('Y-m-d H:i:s');
            $connection->update($versionTable, ['valid_to' => $now], [
                'definition_id = ?' => $definitionId,
                'store_id = ?' => $storeId,
                'valid_to IS NULL',
            ]);
            $connection->insert($versionTable, [
                'definition_id' => $definitionId,
                'store_id' => $storeId,
                'version' => $currentVersion + 1,
                'content_snapshot' => $snapshot,
                'content_hash' => hash('sha256', $snapshot),
                'valid_from' => $now,
            ]);
            $versionId = (int)$connection->fetchOne('SELECT LAST_INSERT_ID()');
            $connection->commit();

            return $versionId;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function validate(
        string $code,
        string $name,
        string $location,
        string $purpose,
        int $storeId,
        string $content
    ): void {
        if (preg_match(self::CODE_PATTERN, $code) !== 1) {
            throw new DomainException('Consent code must use lowercase letters, digits, dots, dashes or underscores.');
        }
        ConsentLocation::assertValid($location);
        if (trim($name) === '' || trim($purpose) === '' || trim($content) === '') {
            throw new DomainException('Consent name, purpose and content are required.');
        }
        if ($storeId < 0) {
            throw new DomainException('Store ID cannot be negative.');
        }
    }

    private function definitionExists(int $definitionId): bool
    {
        $table = $this->resourceConnection->getTableName('kkkonrad_gdpr_consent_definition');

        return (bool)$this->resourceConnection->getConnection()->fetchOne(
            $this->resourceConnection->getConnection()->select()
                ->from($table, ['definition_id'])
                ->where('definition_id = ?', $definitionId)
        );
    }
}
