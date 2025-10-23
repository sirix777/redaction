<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\StartEndRule;
use Test\Sirix\Redaction\NestedArrayConversionTrait;

final class StartEndRuleTest extends TestCase
{
    use NestedArrayConversionTrait;

    public function testPartialMaskWithStartEndVisible(): void
    {
        $redactor = (new Redactor(
            [
                'superpupersecret' => new StartEndRule(5, 4),
                'secret' => new StartEndRule(2, 3),
                'secret2' => new StartEndRule(2, 0),
            ],
            false
        ))
            ->setObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $value = $this->convertNested([
            'superpupersecret' => 'superpupersecret',
            'objecthere' => [
                'secret' => 'donttellanyone',
                'secret2' => 'donttellanyone2',
            ],
        ]);

        $processed = $redactor->redact($value);

        $this->assertSame('super*******cret', $processed['superpupersecret']);
        $this->assertSame('do*********one', $processed['objecthere']->secret);
        $this->assertSame('do*************', $processed['objecthere']->secret2);
    }

    public function testCustomTemplate(): void
    {
        $rule = new StartEndRule(2, 3);
        $redactor = new Redactor(['secret' => $rule], false);
        $redactor->setTemplate('%s(redacted)');

        $processed = $redactor->redact($this->convertNested(['secret' => 'my_secret_value']));

        $this->assertSame('my**********(redacted)', $processed['secret']);
    }

    public function testZeroVisibleStart(): void
    {
        $rule = new StartEndRule(0, 2);
        $redactor = new Redactor(['secret' => $rule], false);
        $processed = $redactor->redact($this->convertNested(['secret' => 'my_secret_value']));

        $this->assertSame('*************ue', $processed['secret']);
    }

    public function testZeroVisibleEnd(): void
    {
        $rule = new StartEndRule(2, 0);
        $redactor = new Redactor(['secret' => $rule], false);
        $processed = $redactor->redact($this->convertNested(['secret' => 'my_secret_value']));

        $this->assertSame('my*************', $processed['secret']);
    }

    public function testShortValues(): void
    {
        $rule = new StartEndRule(2, 2);
        $redactor = new Redactor(['short' => $rule], false);

        $cases = [
            ['value' => 'to', 'expected' => 't*'],
            ['value' => 'tom', 'expected' => 't**'],
            ['value' => 't', 'expected' => 't'],
            ['value' => null, 'expected' => null],
        ];

        foreach ($cases as $i => $case) {
            $processed = $redactor->redact($this->convertNested(['short' => $case['value']]));

            $this->assertSame($case['expected'], $processed['short'], "Failed on case #{$i}");
        }
    }
}
