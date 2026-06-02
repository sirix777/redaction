<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule\Factory;

use Sirix\Redaction\Rule\EmailRule;
use Sirix\Redaction\Rule\FixedValueRule;
use Sirix\Redaction\Rule\FullMaskRule;
use Sirix\Redaction\Rule\NameRule;
use Sirix\Redaction\Rule\NullRule;
use Sirix\Redaction\Rule\OffsetRule;
use Sirix\Redaction\Rule\PhoneRule;
use Sirix\Redaction\Rule\RedactionRuleInterface;
use Sirix\Redaction\Rule\StartEndRule;

final class SharedRuleFactory
{
    public static function startEnd(int $start, int $end): RedactionRuleInterface
    {
        return new StartEndRule($start, $end);
    }

    public static function fullMask(): RedactionRuleInterface
    {
        return new FullMaskRule();
    }

    public static function fixedValue(string $value): RedactionRuleInterface
    {
        return new FixedValueRule($value);
    }

    public static function email(): RedactionRuleInterface
    {
        return new EmailRule();
    }

    public static function phone(): RedactionRuleInterface
    {
        return new PhoneRule();
    }

    public static function name(): RedactionRuleInterface
    {
        return new NameRule();
    }

    public static function null(): RedactionRuleInterface
    {
        return new NullRule();
    }

    public static function offset(int $offset): RedactionRuleInterface
    {
        return new OffsetRule($offset);
    }
}
