<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Domain\DataRights\Anonymization;

use Kkkonrad\Gdpr\Domain\DataRights\Anonymization\PseudonymGenerator;
use PHPUnit\Framework\TestCase;

class PseudonymGeneratorTest extends TestCase
{
    public function testPseudonymsAreStableInsideOperationAndDoNotContainSourceData(): void
    {
        $generator = new PseudonymGenerator();
        $email = $generator->email('operation-a', 'customer', 42);

        self::assertSame($email, $generator->email('operation-a', 'customer', 42));
        self::assertNotSame($email, $generator->email('operation-b', 'customer', 42));
        self::assertStringEndsWith('@example.invalid', $email);
        self::assertStringNotContainsString('42', $email);
    }
}
