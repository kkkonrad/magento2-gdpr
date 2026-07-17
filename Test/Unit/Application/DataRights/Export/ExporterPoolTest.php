<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Application\DataRights\Export;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Api\DataRights\PersonalDataExporterInterface;
use Kkkonrad\Gdpr\Application\DataRights\Export\ExporterPool;
use PHPUnit\Framework\TestCase;

class ExporterPoolTest extends TestCase
{
    public function testSortsExportersAndRejectsDuplicateCodes(): void
    {
        $late = $this->exporter('late', 20);
        $early = $this->exporter('early', 10);
        self::assertSame([$early, $late], (new ExporterPool([$late, $early]))->all());

        $this->expectException(InvalidArgumentException::class);
        new ExporterPool([$early, $this->exporter('early', 30)]);
    }

    private function exporter(string $code, int $priority): PersonalDataExporterInterface
    {
        $exporter = $this->createMock(PersonalDataExporterInterface::class);
        $exporter->method('getCode')->willReturn($code);
        $exporter->method('getPriority')->willReturn($priority);
        return $exporter;
    }
}
