<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Infrastructure\Identity;

use Kkkonrad\Gdpr\Api\CorrelationIdProviderInterface;
use Kkkonrad\Gdpr\Api\RandomIdGeneratorInterface;

class CorrelationIdProvider implements CorrelationIdProviderInterface
{
    private ?string $correlationId = null;

    public function __construct(private readonly RandomIdGeneratorInterface $randomIdGenerator)
    {
    }

    public function get(): string
    {
        return $this->correlationId ??= $this->randomIdGenerator->uuid();
    }
}
