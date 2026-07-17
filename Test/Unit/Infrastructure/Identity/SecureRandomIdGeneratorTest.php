<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Infrastructure\Identity;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Infrastructure\Identity\SecureRandomIdGenerator;
use PHPUnit\Framework\TestCase;

class SecureRandomIdGeneratorTest extends TestCase
{
    public function testGeneratesUuidAndRequestedRandomBytes(): void
    {
        $generator = new SecureRandomIdGenerator();
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $generator->uuid()
        );
        self::assertSame(32, strlen($generator->bytes(32)));
    }

    public function testRejectsUnsafeLength(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new SecureRandomIdGenerator())->bytes(0);
    }
}
