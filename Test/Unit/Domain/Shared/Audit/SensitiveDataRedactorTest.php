<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Domain\Shared\Audit;

use Kkkonrad\Gdpr\Domain\Shared\Audit\SensitiveDataRedactor;
use PHPUnit\Framework\TestCase;

final class SensitiveDataRedactorTest extends TestCase
{
    public function testRedactsSensitiveKeysRecursivelyAndDropsObjects(): void
    {
        $result = (new SensitiveDataRedactor())->redact([
            'processor' => 'customer',
            'email' => 'person@example.com',
            'nested' => ['access_token' => 'secret', 'count' => 2],
            'object' => new \stdClass(),
        ]);

        self::assertSame('customer', $result['processor']);
        self::assertSame('[REDACTED]', $result['email']);
        self::assertSame('[REDACTED]', $result['nested']['access_token']);
        self::assertSame(2, $result['nested']['count']);
        self::assertArrayNotHasKey('object', $result);
    }

    public function testRedactsSensitiveValuesEmbeddedInMessages(): void
    {
        $result = (new SensitiveDataRedactor())->redact([
            'message' => 'Failed for person@example.com with Bearer abc.def and token=abc123',
        ]);

        self::assertStringNotContainsString('person@example.com', $result['message']);
        self::assertStringNotContainsString('abc.def', $result['message']);
        self::assertStringNotContainsString('abc123', $result['message']);
    }
}
