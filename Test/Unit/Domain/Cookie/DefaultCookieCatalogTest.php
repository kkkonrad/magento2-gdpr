<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Domain\Cookie;

use Kkkonrad\Gdpr\Domain\Cookie\DefaultCookieCatalog;
use PHPUnit\Framework\TestCase;

class DefaultCookieCatalogTest extends TestCase
{
    public function testCatalogContainsFourUniqueSemanticGroupsAndMagentoEssentials(): void
    {
        $catalog = new DefaultCookieCatalog();
        $groups = $catalog->groups();
        $storage = $catalog->storage();

        self::assertSame(
            ['essential', 'functionality', 'statistical', 'marketing'],
            array_column($groups, 'code')
        );
        self::assertSame(1, $groups[0]['required']);
        self::assertCount(11, $storage);
        self::assertCount(11, array_unique(array_map(
            static fn (array $item): string => $item['type'] . ':' . $item['pattern'],
            $storage
        )));
        self::assertContains('form_key', array_column($storage, 'pattern'));
        self::assertContains('mage-cache-storage', array_column($storage, 'pattern'));
    }
}
