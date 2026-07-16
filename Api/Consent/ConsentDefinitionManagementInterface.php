<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api\Consent;

interface ConsentDefinitionManagementInterface
{
    /**
     * Save a consent definition and its store-view draft.
     */
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
    ): int;

    /**
     * Publish the current store-view draft as an immutable version.
     */
    public function publish(int $definitionId, int $storeId): int;
}
