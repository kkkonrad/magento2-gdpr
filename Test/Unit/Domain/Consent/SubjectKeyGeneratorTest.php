<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Domain\Consent;

use DomainException;
use Kkkonrad\Gdpr\Domain\Consent\SubjectKeyGenerator;
use Kkkonrad\Gdpr\Infrastructure\Identity\SecureRandomIdGenerator;
use PHPUnit\Framework\TestCase;

class SubjectKeyGeneratorTest extends TestCase
{
    public function testGeneratesNonIdentifyingRandomKey(): void
    {
        $generator = new SubjectKeyGenerator(new SecureRandomIdGenerator());
        $first = $generator->generate();
        $second = $generator->generate();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
        self::assertNotSame($first, $second);
        $generator->assertValid($first);
    }

    public function testRejectsInvalidKey(): void
    {
        $this->expectException(DomainException::class);
        (new SubjectKeyGenerator(new SecureRandomIdGenerator()))->assertValid('customer@example.com');
    }
}
