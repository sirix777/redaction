<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\OffsetRule;
use Sirix\Redaction\Rule\StartEndRule;

final class RuleValidationTest extends TestCase
{
    public function testStartEndRuleRejectsNegativeVisibleStart(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StartEndRule(-1, 2);
    }

    public function testStartEndRuleRejectsNegativeVisibleEnd(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new StartEndRule(1, -2);
    }

    public function testOffsetRuleAllowsNegativeOffset(): void
    {
        $redactor = new Redactor([
            'secret' => new OffsetRule(-2),
        ], false);

        $this->assertSame('****et', $redactor->redact(['secret' => 'secret'])['secret']);
    }

    public function testTemplateRejectsWidthSpecifier(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Redactor([], false))->withTemplate('%100s');
    }

    public function testTemplateRejectsMultiplePlaceholders(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new Redactor([], false))->withTemplate('%s%s');
    }

    public function testSafeTemplateIsAccepted(): void
    {
        $redactor = (new Redactor([], false))->withTemplate('[%s]');

        $this->assertSame('[%s]', $redactor->getTemplate());
    }
}
