<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Bridge\Monolog;

use DateTimeImmutable;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Bridge\Monolog\RedactorProcessor;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\OffsetRule;
use Sirix\Redaction\Rule\StartEndRule;
use stdClass;

final class RedactorProcessorTest extends TestCase
{
    public function testProcessesSimpleKey(): void
    {
        $redactor = new Redactor([
            'password' => new OffsetRule(3),
        ], false);

        $redactorProcessor = new RedactorProcessor($redactor);

        $logRecord = $this->createRecord([
            'username' => 'alice',
            'password' => 'secret123',
        ]);

        $processed = $redactorProcessor($logRecord);

        $this->assertSame('sec******', $processed->context['password']);
        $this->assertSame('alice', $processed->context['username']);
    }

    public function testProcessesNestedArray(): void
    {
        $redactor = new Redactor([
            'password' => new OffsetRule(2),
            'token' => new OffsetRule(4),
        ], false);

        $redactorProcessor = new RedactorProcessor($redactor);

        $logRecord = $this->createRecord([
            'user' => [
                'username' => 'bob',
                'password' => 'secret123',
                'token' => 'abcd1234',
            ],
        ]);

        $processed = $redactorProcessor($logRecord);

        $this->assertSame('se*******', $processed->context['user']['password']);
        $this->assertSame('abcd****', $processed->context['user']['token']);
        $this->assertSame('bob', $processed->context['user']['username']);
    }

    public function testProcessesNestedArrayWithTopLevelRules(): void
    {
        $redactor = new Redactor([
            'password' => new OffsetRule(2),
            'token' => new OffsetRule(4),
        ], false);

        $redactorProcessor = new RedactorProcessor($redactor);

        $logRecord = $this->createRecord([
            'user' => [
                'username' => 'bob',
                'password' => 'secret123',
                'token' => 'abcd1234',
            ],
        ]);

        $processed = $redactorProcessor($logRecord);

        $this->assertSame('se*******', $processed->context['user']['password']);
        $this->assertSame('abcd****', $processed->context['user']['token']);
        $this->assertSame('bob', $processed->context['user']['username']);
    }

    public function testProcessesObjectProperties(): void
    {
        $user = new stdClass();
        $user->username = 'carol';
        $user->password = 'mypass';

        $redactor = (new Redactor(
            [
                'password' => new OffsetRule(2),
            ],
            false
        ))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $redactorProcessor = new RedactorProcessor($redactor);

        $logRecord = $this->createRecord(['user' => $user]);

        $processed = $redactorProcessor($logRecord);

        $this->assertInstanceOf(stdClass::class, $processed->context['user']);
        $this->assertSame('my****', $processed->context['user']->password);
        $this->assertSame('carol', $processed->context['user']->username);
        $this->assertSame('mypass', $user->password);
    }

    public function testProcessesPartialMaskWithStartEndRule(): void
    {
        $redactor = (new Redactor([
            'secret' => new StartEndRule(2, 3),
        ], false))
            ->withTemplate('%s(redacted)')
        ;

        $redactorProcessor = new RedactorProcessor($redactor);

        $logRecord = $this->createRecord([
            'secret' => 'my_secret_value',
        ]);

        $processed = $redactorProcessor($logRecord);

        $this->assertSame('my**********(redacted)', $processed->context['secret']);
    }

    public function testHandlesNestedObjectsAndArrays(): void
    {
        $nested = new stdClass();
        $nested->field1 = 'abcdef';
        $nested->field2 = '123456';

        $redactor = (new Redactor(
            [
                'field1' => new OffsetRule(2),
                'field2' => new OffsetRule(3),
            ],
            false
        ))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $redactorProcessor = new RedactorProcessor($redactor);

        $logRecord = $this->createRecord(['nested' => $nested]);
        $processed = $redactorProcessor($logRecord);

        $this->assertSame('ab****', $processed->context['nested']->field1);
        $this->assertSame('123***', $processed->context['nested']->field2);
    }

    public function testAllowsDisablingDefaultRules(): void
    {
        $redactor = new Redactor([], false);

        $redactorProcessor = new RedactorProcessor($redactor);

        $logRecord = $this->createRecord([
            'password' => 'mypassword',
            'token' => 'abcd',
        ]);

        $processed = $redactorProcessor($logRecord);

        $this->assertSame('mypassword', $processed->context['password']);
        $this->assertSame('abcd', $processed->context['token']);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function createRecord(array $context): LogRecord
    {
        return new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: $context,
            extra: []
        );
    }
}
