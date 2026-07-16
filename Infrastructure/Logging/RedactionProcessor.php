<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Infrastructure\Logging;

use Kkkonrad\Gdpr\Domain\Shared\Audit\SensitiveDataRedactor;
use Monolog\LogRecord;

class RedactionProcessor
{
    public function __construct(private readonly SensitiveDataRedactor $redactor)
    {
    }

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redactor->redact($record->context),
            extra: $this->redactor->redact($record->extra)
        );
    }
}
