<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Domain\Shared\Job;

use InvalidArgumentException;

final class JobProcessorPool
{
    /** @var array<string, JobProcessorInterface> */
    private array $processors = [];

    /** @param JobProcessorInterface[] $processors */
    public function __construct(array $processors = [])
    {
        foreach ($processors as $processor) {
            if (!$processor instanceof JobProcessorInterface) {
                throw new InvalidArgumentException('Every GDPR job processor must implement JobProcessorInterface.');
            }
            $type = $processor->getType();
            if ($type === '' || isset($this->processors[$type])) {
                throw new InvalidArgumentException(sprintf('Duplicate or empty GDPR job processor type "%s".', $type));
            }
            $this->processors[$type] = $processor;
        }
    }

    public function get(string $type): JobProcessorInterface
    {
        if (!isset($this->processors[$type])) {
            throw new InvalidArgumentException(sprintf('No GDPR job processor is registered for type "%s".', $type));
        }

        return $this->processors[$type];
    }
}
