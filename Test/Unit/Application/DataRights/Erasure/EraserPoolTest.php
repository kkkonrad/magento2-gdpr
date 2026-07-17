<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Application\DataRights\Erasure;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Api\DataRights\PersonalDataEraserInterface;
use Kkkonrad\Gdpr\Application\DataRights\Erasure\EraserPool;
use PHPUnit\Framework\TestCase;

class EraserPoolTest extends TestCase
{
    public function testSortsErasersAndRejectsDuplicateCodes(): void
    {
        $core = $this->eraser('core', 1000);
        $extension = $this->eraser('extension', 100);
        self::assertSame([$extension, $core], (new EraserPool([$core, $extension]))->all());

        $this->expectException(InvalidArgumentException::class);
        new EraserPool([$core, $this->eraser('core', 2000)]);
    }

    private function eraser(string $code, int $priority): PersonalDataEraserInterface
    {
        $eraser = $this->createMock(PersonalDataEraserInterface::class);
        $eraser->method('getCode')->willReturn($code);
        $eraser->method('getPriority')->willReturn($priority);
        return $eraser;
    }
}
