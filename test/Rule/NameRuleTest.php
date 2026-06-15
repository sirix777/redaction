<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\NameRule;
use Test\Sirix\Redaction\NestedArrayConversionTrait;

final class NameRuleTest extends TestCase
{
    use NestedArrayConversionTrait;

    public function testNameRedaction(): void
    {
        $nameRule = new NameRule();
        $redactor = new Redactor([
            'name' => $nameRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'name' => 'John',
        ]));

        $this->assertSame('Jo***n', $processed['name']);
    }

    public function testLongNameRedaction(): void
    {
        $nameRule = new NameRule();
        $redactor = new Redactor([
            'name' => $nameRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'name' => 'Alexander',
        ]));

        $this->assertSame('Al***r', $processed['name']);
    }

    public function testMultipleNamesRedaction(): void
    {
        $nameRule = new NameRule();
        $redactor = new Redactor([
            'fullname' => $nameRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'fullname' => 'John Doe Smith',
        ]));

        $this->assertSame('Jo***n Do***e Sm***h', $processed['fullname']);
    }

    public function testNameRedactionInNestedStructures(): void
    {
        $nameRule = new NameRule();
        $redactor = (new Redactor(
            [
                'name' => $nameRule,
            ],
            false
        ))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $processed = $redactor->redact($this->convertNested([
            'user' => [
                'profile' => [
                    'name' => 'Maria',
                ],
            ],
        ]));

        $this->assertSame('Ma***a', $processed['user']->profile->name);
    }

    public function testTooShortName(): void
    {
        $nameRule = new NameRule();
        $redactor = new Redactor([
            'name' => $nameRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'name' => 'Jo',
        ]));

        $this->assertSame('J*', $processed['name']);
    }
}
