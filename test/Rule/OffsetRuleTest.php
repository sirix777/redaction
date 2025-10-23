<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\OffsetRule;
use Test\Sirix\Redaction\NestedArrayConversionTrait;

final class OffsetRuleTest extends TestCase
{
    use NestedArrayConversionTrait;

    public function testSimpleOffsetRule(): void
    {
        $rule = new OffsetRule(3);
        $redactor = new Redactor(['password' => $rule], false);
        $processed = $redactor->redact($this->convertNested(['username' => 'alice', 'password' => 'secret123']));

        $this->assertSame('sec******', $processed['password']);
        $this->assertSame('alice', $processed['username']); // Unaffected field
    }

    public function testOffsetRuleInNestedArray(): void
    {
        $redactor = (new Redactor(
            [
                'password' => new OffsetRule(2),
                'token' => new OffsetRule(4),
            ],
            false
        ))
            ->setObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $processed = $redactor->redact($this->convertNested([
            'user' => [
                'username' => 'bob',
                'password' => 'secret123',
                'token' => 'abcd1234',
            ],
        ]));

        $this->assertSame('se*******', $processed['user']->password);
        $this->assertSame('abcd****', $processed['user']->token);
        $this->assertSame('bob', $processed['user']->username);
    }

    public function testNegativeOffset(): void
    {
        $rule = new OffsetRule(-2);
        $redactor = new Redactor(['password' => $rule], false);
        $processed = $redactor->redact($this->convertNested(['password' => 'secret123']));

        $this->assertSame('*******23', $processed['password']);
    }
}
