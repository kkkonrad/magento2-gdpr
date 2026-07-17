<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Consent;

use Kkkonrad\Gdpr\Api\RandomIdGeneratorInterface;

class SubjectKeyGenerator
{
    public function __construct(private readonly RandomIdGeneratorInterface $randomIdGenerator)
    {
    }

    public function generate(): string
    {
        return bin2hex($this->randomIdGenerator->bytes(32));
    }

    public function assertValid(string $subjectKey): void
    {
        if (preg_match('/^[a-f0-9]{64}$/', $subjectKey) !== 1) {
            throw new \DomainException('The consent subject key is invalid.');
        }
    }
}
