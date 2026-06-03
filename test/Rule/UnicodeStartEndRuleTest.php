<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\Factory\SharedRuleFactory;
use Sirix\Redaction\Rule\UnicodeStartEndRule;

final class UnicodeStartEndRuleTest extends TestCase
{
    public function testMasksUnicodeValueByGraphemeCharacters(): void
    {
        $redactor = new Redactor([
            'secret' => new UnicodeStartEndRule(2, 2),
        ], false);

        $result = $redactor->redact(['secret' => 'привет-мир']);

        $this->assertSame('пр******ир', $result['secret']);
    }

    public function testLengthLimitCountsGraphemeCharacters(): void
    {
        $redactor = (new Redactor([
            'secret' => new UnicodeStartEndRule(2, 2),
        ], false))
            ->withLengthLimit(5)
        ;

        $result = $redactor->redact(['secret' => 'привет-мир']);

        $this->assertSame('пр***', $result['secret']);
    }

    public function testUnicodeReplacementDoesNotExceedCharacterLimit(): void
    {
        $redactor = (new Redactor([
            'secret' => new UnicodeStartEndRule(0, 0),
        ], false))
            ->withReplacement('🔒')
            ->withLengthLimit(3)
        ;

        $result = $redactor->redact(['secret' => 'секрет']);

        $this->assertSame('🔒🔒🔒', $result['secret']);
    }

    public function testSharedRuleFactoryCreatesUnicodeStartEndRule(): void
    {
        $redactionRule = SharedRuleFactory::unicodeStartEnd(1, 1);
        $redactor = new Redactor(['secret' => $redactionRule], false);

        $this->assertSame('п****т', $redactor->redact(['secret' => 'привет'])['secret']);
    }

    public function testRejectsNegativeVisibleStart(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UnicodeStartEndRule(-1, 1);
    }

    public function testRejectsNegativeVisibleEnd(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new UnicodeStartEndRule(1, -1);
    }
}
