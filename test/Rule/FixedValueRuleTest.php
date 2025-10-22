<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\FixedValueRule;
use Test\Sirix\Redaction\NestedArrayConversionTrait;

final class FixedValueRuleTest extends TestCase
{
    use NestedArrayConversionTrait;

    public function testFixedValueRule(): void
    {
        $rule = new FixedValueRule('REDACTED');
        $redactor = new Redactor(['token' => $rule], false);
        $processed = $redactor->redact($this->convertNested(['token' => 'abcd1234']));

        $this->assertSame('REDACTED', $processed['token']);
    }

    public function testCustomReplacementValue(): void
    {
        $rule = new FixedValueRule('CUSTOM_VALUE');
        $redactor = new Redactor(['secret' => $rule], false);
        $processed = $redactor->redact($this->convertNested(['secret' => 'sensitive-data']));

        $this->assertSame('CUSTOM_VALUE', $processed['secret']);
    }
}
