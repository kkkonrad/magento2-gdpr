<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Test\Unit\Domain\Shared\Job;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobContext;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorInterface;
use Kkkonrad\Gdpr\Domain\Shared\Job\JobProcessorPool;
use PHPUnit\Framework\TestCase;

class JobProcessorPoolTest extends TestCase
{
    public function testReturnsProcessorByType(): void
    {
        $processor = new class implements JobProcessorInterface
        {
            public function getType(): string
            {
                return 'export';
            }

            public function process(JobContext $context): void
            {
            }
        };

        self::assertSame($processor, (new JobProcessorPool([$processor]))->get('export'));
    }

    public function testRejectsDuplicateProcessorTypes(): void
    {
        $first = $this->createMock(JobProcessorInterface::class);
        $first->method('getType')->willReturn('erase');
        $second = $this->createMock(JobProcessorInterface::class);
        $second->method('getType')->willReturn('erase');

        $this->expectException(InvalidArgumentException::class);
        new JobProcessorPool([$first, $second]);
    }

    public function testRejectsMissingProcessor(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new JobProcessorPool())->get('missing');
    }
}
