<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Shared\Job;

class JobContext
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $configSnapshot
     */
    public function __construct(
        public int $jobId,
        public string $publicId,
        public string $type,
        public string $featureCode,
        public int $storeId,
        public ?int $requestId,
        public array $payload,
        public array $configSnapshot,
        public ?string $checkpoint
    ) {
    }

    /** @param array<string, mixed> $row */
    public static function fromRow(array $row): self
    {
        return new self(
            (int)$row['job_id'],
            (string)$row['public_id'],
            (string)$row['type'],
            (string)$row['feature_code'],
            (int)$row['store_id'],
            $row['request_id'] !== null ? (int)$row['request_id'] : null,
            self::decodeJson($row['payload_json'] ?? null),
            self::decodeJson($row['config_snapshot_json'] ?? null),
            $row['checkpoint'] !== null ? (string)$row['checkpoint'] : null
        );
    }

    /** @return array<string, mixed> */
    private static function decodeJson(mixed $value): array
    {
        if (!is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        return is_array($decoded) ? $decoded : [];
    }
}
