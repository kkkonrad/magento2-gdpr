<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Application\DataRights\Anonymization;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Api\DataRights\PersonalDataAnonymizerInterface;
use Kkkonrad\Gdpr\Application\DataRights\Anonymization\AnonymizerPool;
use PHPUnit\Framework\TestCase;

class AnonymizerPoolTest extends TestCase
{
    public function testSortsProcessorsByPriorityAndCode(): void
    {
        $late = $this->processor('late', 20);
        $second = $this->processor('second', 10);
        $first = $this->processor('first', 10);

        self::assertSame([$first, $second, $late], (new AnonymizerPool([$late, $second, $first]))->all());
    }

    public function testRejectsDuplicateCodes(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new AnonymizerPool([$this->processor('duplicate', 10), $this->processor('duplicate', 20)]);
    }

    private function processor(string $code, int $priority): PersonalDataAnonymizerInterface
    {
        $processor = $this->createMock(PersonalDataAnonymizerInterface::class);
        $processor->method('getCode')->willReturn($code);
        $processor->method('getPriority')->willReturn($priority);
        return $processor;
    }
}
