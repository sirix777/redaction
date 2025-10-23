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

use function md5;

final class SharedRuleFactory
{
    /** @var array<string, RedactionRuleInterface> */
    private static array $cache = [];

    public static function startEnd(int $start, int $end): RedactionRuleInterface
    {
        return self::getOrCreate("start_end_{$start}_{$end}", fn () => new StartEndRule($start, $end));
    }

    public static function fullMask(): RedactionRuleInterface
    {
        return self::getOrCreate('full_mask', fn () => new FullMaskRule());
    }

    public static function fixedValue(string $value): RedactionRuleInterface
    {
        return self::getOrCreate('fixed_' . md5($value), fn () => new FixedValueRule($value));
    }

    public static function email(): RedactionRuleInterface
    {
        return self::getOrCreate('email', fn () => new EmailRule());
    }

    public static function phone(): RedactionRuleInterface
    {
        return self::getOrCreate('phone', fn () => new PhoneRule());
    }

    public static function name(): RedactionRuleInterface
    {
        return self::getOrCreate('name', fn () => new NameRule());
    }

    public static function null(): RedactionRuleInterface
    {
        return self::getOrCreate('null', fn () => new NullRule());
    }

    public static function offset(int $offset): RedactionRuleInterface
    {
        return self::getOrCreate("offset_{$offset}", fn () => new OffsetRule($offset));
    }

    public static function clearCache(): void
    {
        self::$cache = [];
    }

    private static function getOrCreate(string $key, callable $factory): RedactionRuleInterface
    {
        if (! isset(self::$cache[$key])) {
            self::$cache[$key] = $factory();
        }

        return self::$cache[$key];
    }
}
