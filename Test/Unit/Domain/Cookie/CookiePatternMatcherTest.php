<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Domain\Cookie;

use DomainException;
use Kkkonrad\Gdpr\Domain\Cookie\CookiePatternMatcher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CookiePatternMatcherTest extends TestCase
{
    #[DataProvider('matchProvider')]
    public function testMatchesExactAndSuffixWildcard(string $pattern, string $name, bool $expected): void
    {
        self::assertSame($expected, (new CookiePatternMatcher())->matches($pattern, $name));
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function matchProvider(): array
    {
        return [
            'exact' => ['form_key', 'form_key', true],
            'exact mismatch' => ['form_key', 'mage-cache-sessid', false],
            'wildcard' => ['mage-cache-*', 'mage-cache-sessid', true],
            'wildcard mismatch' => ['mage-cache-*', 'PHPSESSID', false],
        ];
    }

    public function testRejectsWildcardInTheMiddle(): void
    {
        $this->expectException(DomainException::class);
        (new CookiePatternMatcher())->assertValid('ga_*_value');
    }

    public function testDetectsOverlappingPatterns(): void
    {
        $this->expectException(DomainException::class);
        (new CookiePatternMatcher())->assertNoConflicts(['_ga*', '_ga_ABC']);
    }
}
