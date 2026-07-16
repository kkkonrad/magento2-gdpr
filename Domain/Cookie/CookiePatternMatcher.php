<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Cookie;

use DomainException;

class CookiePatternMatcher
{
    public function assertValid(string $pattern): void
    {
        if ($pattern === '' || strlen($pattern) > 255) {
            throw new DomainException('Cookie pattern must contain between 1 and 255 characters.');
        }
        if (str_contains(substr($pattern, 0, -1), '*') || substr_count($pattern, '*') > 1) {
            throw new DomainException('Cookie wildcard is supported only once and only as the final character.');
        }
        if (preg_match('/^[A-Za-z0-9_.-]+\*?$/', $pattern) !== 1) {
            throw new DomainException('Cookie pattern contains unsupported characters.');
        }
    }

    public function matches(string $pattern, string $cookieName): bool
    {
        $this->assertValid($pattern);
        if (str_ends_with($pattern, '*')) {
            return str_starts_with($cookieName, substr($pattern, 0, -1));
        }

        return hash_equals($pattern, $cookieName);
    }

    /**
     * @param string[] $patterns
     */
    public function assertNoConflicts(array $patterns): void
    {
        foreach ($patterns as $index => $pattern) {
            $this->assertValid($pattern);
            foreach (array_slice($patterns, $index + 1) as $otherPattern) {
                $this->assertValid($otherPattern);
                $firstPrefix = rtrim($pattern, '*');
                $secondPrefix = rtrim($otherPattern, '*');
                if ($pattern === $otherPattern
                    || (str_ends_with($pattern, '*') && str_starts_with($secondPrefix, $firstPrefix))
                    || (str_ends_with($otherPattern, '*') && str_starts_with($firstPrefix, $secondPrefix))
                ) {
                    throw new DomainException(sprintf(
                        'Cookie patterns "%s" and "%s" overlap.',
                        $pattern,
                        $otherPattern
                    ));
                }
            }
        }
    }
}
