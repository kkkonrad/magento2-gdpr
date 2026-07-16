<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Domain\Consent;

use DomainException;
use Kkkonrad\Gdpr\Domain\Consent\SubjectKeyGenerator;
use PHPUnit\Framework\TestCase;

class SubjectKeyGeneratorTest extends TestCase
{
    public function testGeneratesNonIdentifyingRandomKey(): void
    {
        $generator = new SubjectKeyGenerator();
        $first = $generator->generate();
        $second = $generator->generate();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
        self::assertNotSame($first, $second);
        $generator->assertValid($first);
    }

    public function testRejectsInvalidKey(): void
    {
        $this->expectException(DomainException::class);
        (new SubjectKeyGenerator())->assertValid('customer@example.com');
    }
}
