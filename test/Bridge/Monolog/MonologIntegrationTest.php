<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Bridge\Monolog;

use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Bridge\Monolog\RedactorProcessor;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\OffsetRule;
use Sirix\Redaction\Rule\RedactionRuleInterface;

final class MonologIntegrationTest extends TestCase
{
    public function testProcessorRedactsLogContext(): void
    {
        [$logger, $handler] = $this->createLogger([
            'password' => new OffsetRule(3),
        ], false);

        $logger->info('User login', [
            'username' => 'alice',
            'password' => 'secret123',
        ]);

        $record = $handler->getRecords()[0];

        $this->assertSame('sec******', $record->context['password']);
        $this->assertSame('alice', $record->context['username']);
        $this->assertSame('User login', $record->message);
    }

    public function testProcessorWithNestedContext(): void
    {
        [$logger, $handler] = $this->createLogger([
            'token' => new OffsetRule(4),
        ], false);

        $logger->info('Nested context', [
            'user' => [
                'username' => 'bob',
                'token' => 'abcd1234',
            ],
        ]);

        $record = $handler->getRecords()[0];

        $this->assertSame('abcd****', $record->context['user']['token']);
        $this->assertSame('bob', $record->context['user']['username']);
    }

    public function testProcessorPreservesNonSensitiveData(): void
    {
        [$logger, $handler] = $this->createLogger([], false);

        $logger->info('No redaction', [
            'some_key' => 'value',
        ]);

        $record = $handler->getRecords()[0];

        $this->assertSame('value', $record->context['some_key']);
        $this->assertSame('No redaction', $record->message);
    }

    public function testDefaultRulesMaskPassword(): void
    {
        [$logger, $handler] = $this->createLogger();

        $logger->info('User login', [
            'username' => 'alice',
            'password' => 'secret123',
        ]);

        $record = $handler->getRecords()[0];

        $this->assertSame('*', $record->context['password']);
        $this->assertSame('alice', $record->context['username']);
    }

    public function testDefaultRulesMaskCreditCard(): void
    {
        [$logger, $handler] = $this->createLogger();

        $logger->info('Payment info', [
            'cardNum' => '4111111145551142',
        ]);

        $record = $handler->getRecords()[0];

        $this->assertSame('411111******1142', $record->context['cardNum']);
    }

    public function testDefaultRulesMaskEmail(): void
    {
        [$logger, $handler] = $this->createLogger();

        $logger->info('Customer email', [
            'email' => 'alice@example.com',
        ]);

        $record = $handler->getRecords()[0];

        $this->assertEquals('ali****@example.com', $record->context['email']);
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     *
     * @return array{0: Logger, 1: TestHandler}
     */
    private function createLogger(array $rules = [], bool $useDefaultRules = true): array
    {
        $redactor = new Redactor($rules, $useDefaultRules);
        $redactorProcessor = new RedactorProcessor($redactor);
        $logger = new Logger('test');
        $logger->pushProcessor($redactorProcessor);
        $testHandler = new TestHandler();
        $logger->pushHandler($testHandler);

        return [$logger, $testHandler];
    }
}
