<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\EmailRule;
use Sirix\Redaction\Rule\FullMaskRule;
use Sirix\Redaction\Rule\NameRule;
use Sirix\Redaction\Rule\OffsetRule;
use Sirix\Redaction\Rule\PhoneRule;
use Sirix\Redaction\Rule\StartEndRule;

use function str_repeat;
use function strlen;

final class RuleLengthLimitTest extends TestCase
{
    public function testStartEndRuleRespectsLengthLimit(): void
    {
        $redactor = (new Redactor([
            'secret' => new StartEndRule(2, 2),
        ], false))
            ->withLengthLimit(5)
        ;

        $result = $redactor->redact([
            'secret' => 'my_secret_value',
        ]);

        $this->assertSame('my***', $result['secret']);
    }

    public function testStartEndRuleShortValueDoesNotExceedLengthLimit(): void
    {
        $redactor = (new Redactor([
            'secret' => new StartEndRule(2, 2),
        ], false))
            ->withLengthLimit(2)
        ;

        $result = $redactor->redact([
            'secret' => 'abc',
        ]);

        $this->assertSame('a*', $result['secret']);
        $this->assertSame(2, strlen($result['secret']));
    }

    public function testStartEndRuleShortValueRespectsZeroLengthLimit(): void
    {
        $redactor = (new Redactor([
            'secret' => new StartEndRule(2, 2),
        ], false))
            ->withLengthLimit(0)
        ;

        $result = $redactor->redact([
            'secret' => 'abc',
        ]);

        $this->assertSame('', $result['secret']);
    }

    public function testOffsetRuleRespectsLengthLimit(): void
    {
        $redactor = (new Redactor([
            'secret' => new OffsetRule(2),
        ], false))
            ->withLengthLimit(5)
        ;

        $result = $redactor->redact([
            'secret' => 'my_secret_value',
        ]);

        $this->assertSame('my***', $result['secret']);
    }

    public function testFullMaskRuleRespectsLengthLimit(): void
    {
        $redactor = (new Redactor([
            'secret' => new FullMaskRule(),
        ], false))
            ->withLengthLimit(5)
        ;

        $result = $redactor->redact([
            'secret' => 'my_secret_value',
        ]);

        $this->assertSame('*****', $result['secret']);
    }

    public function testMultiCharacterReplacementDoesNotExceedLengthLimit(): void
    {
        $redactor = (new Redactor([
            'secret' => new FullMaskRule(),
        ], false))
            ->withReplacement('##')
            ->withLengthLimit(5)
        ;

        $result = $redactor->redact([
            'secret' => 'my_secret_value',
        ]);

        $this->assertSame('#####', $result['secret']);
        $this->assertSame(5, strlen($result['secret']));
    }

    public function testEmailRuleRespectsLengthLimit(): void
    {
        $redactor = (new Redactor([
            'email' => new EmailRule(),
        ], false))
            ->withLengthLimit(10)
        ;

        $result = $redactor->redact([
            'email' => 'john.doe@example.com',
        ]);

        $this->assertSame('joh****@ex', $result['email']);
    }

    public function testPhoneRuleRespectsLengthLimit(): void
    {
        $redactor = (new Redactor([
            'phone' => new PhoneRule(),
        ], false))
            ->withLengthLimit(8)
        ;

        $result = $redactor->redact([
            'phone' => '1234567890',
        ]);

        $this->assertSame('1234****', $result['phone']);
    }

    public function testNameRuleRespectsLengthLimit(): void
    {
        $redactor = (new Redactor([
            'name' => new NameRule(),
        ], false))
            ->withLengthLimit(5)
        ;

        $result = $redactor->redact([
            'name' => 'Alexander',
        ]);

        $this->assertSame('Al***', $result['name']);
    }

    public function testLengthLimitIsByteBasedForBuiltInRules(): void
    {
        $redactor = (new Redactor([
            'secret' => new StartEndRule(2, 2),
        ], false))
            ->withLengthLimit(5)
        ;

        $result = $redactor->redact([
            'secret' => 'привет-мир',
        ]);

        $this->assertSame('п***', $result['secret']);
        $this->assertSame(5, strlen($result['secret']));
    }

    public function testVeryLongInputWithLengthLimitReturnsBoundedResult(): void
    {
        $redactor = (new Redactor([
            'secret' => new StartEndRule(2, 2),
            'email'  => new EmailRule(),
        ], false))
            ->withLengthLimit(10)
        ;

        $result = $redactor->redact([
            'secret' => str_repeat('a', 100_000),
            'email'  => 'aaa@' . str_repeat('example', 100_000),
        ]);

        $this->assertSame(10, strlen((string) $result['secret']));
        $this->assertSame(10, strlen((string) $result['email']));
    }
}
