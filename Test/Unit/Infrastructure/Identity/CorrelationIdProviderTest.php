<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Infrastructure\Identity;

use Kkkonrad\Gdpr\Api\RandomIdGeneratorInterface;
use Kkkonrad\Gdpr\Infrastructure\Identity\CorrelationIdProvider;
use PHPUnit\Framework\TestCase;

class CorrelationIdProviderTest extends TestCase
{
    public function testReturnsOneStableIdForServiceLifetime(): void
    {
        $random = $this->createMock(RandomIdGeneratorInterface::class);
        $random->expects(self::once())->method('uuid')->willReturn('00000000-0000-4000-8000-000000000001');
        $provider = new CorrelationIdProvider($random);

        self::assertSame('00000000-0000-4000-8000-000000000001', $provider->get());
        self::assertSame('00000000-0000-4000-8000-000000000001', $provider->get());
    }
}
