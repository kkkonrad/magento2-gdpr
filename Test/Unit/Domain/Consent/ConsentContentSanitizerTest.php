<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Domain\Consent;

use Kkkonrad\Gdpr\Domain\Consent\ConsentContentSanitizer;
use PHPUnit\Framework\TestCase;

class ConsentContentSanitizerTest extends TestCase
{
    public function testKeepsAllowedFormattingAndSafeLinks(): void
    {
        $result = (new ConsentContentSanitizer())->sanitize(
            '<p onclick="bad()">Read <strong>this</strong> <a href="https://example.com/policy">policy</a>.</p>'
        );

        self::assertStringContainsString('<strong>this</strong>', $result);
        self::assertStringContainsString('href="https://example.com/policy"', $result);
        self::assertStringNotContainsString('onclick', $result);
    }

    public function testRemovesDangerousElementsAndLinkProtocols(): void
    {
        $result = (new ConsentContentSanitizer())->sanitize(
            '<script>alert(1)</script><a href="javascript:alert(2)">bad</a><em>ok</em>'
        );

        self::assertStringNotContainsString('<script', $result);
        self::assertStringNotContainsString('javascript:', $result);
        self::assertStringContainsString('<em>ok</em>', $result);
    }
}
