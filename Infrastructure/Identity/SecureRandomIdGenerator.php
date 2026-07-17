<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Infrastructure\Identity;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Api\RandomIdGeneratorInterface;
use Ramsey\Uuid\Uuid;

class SecureRandomIdGenerator implements RandomIdGeneratorInterface
{
    public function uuid(): string
    {
        return Uuid::uuid4()->toString();
    }

    public function bytes(int $length): string
    {
        if ($length < 1 || $length > 4096) {
            throw new InvalidArgumentException('Random byte length must be between 1 and 4096.');
        }
        return random_bytes($length);
    }
}
