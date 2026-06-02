<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Bridge\Monolog;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Bridge\Monolog\RedactorProcessor;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\OffsetRule;

final class RedactorProcessorScopeTest extends TestCase
{
    public function testProcessorOnlyRedactsContext(): void
    {
        $processor = new RedactorProcessor(new Redactor([
            'password' => new OffsetRule(2),
        ], false));

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'password=secret',
            context: ['password' => 'secret'],
            extra: ['password' => 'secret'],
        );

        $processed = $processor($record);

        $this->assertSame('se****', $processed->context['password']);
        $this->assertSame('password=secret', $processed->message);
        $this->assertSame('secret', $processed->extra['password']);
    }
}
