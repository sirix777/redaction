<?php

declare(strict_types=1);

namespace Sirix\Redaction\Bridge\Monolog;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Sirix\Redaction\RedactorInterface;

final readonly class RedactorProcessor implements ProcessorInterface
{
    public function __construct(private RedactorInterface $redactor) {}

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(context: $this->redactor->redact($record->context));
    }
}
