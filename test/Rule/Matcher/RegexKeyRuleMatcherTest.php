<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule\Matcher;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Rule\Factory\SharedRuleFactory;
use Sirix\Redaction\Rule\Matcher\RegexKeyRuleMatcher;

final class RegexKeyRuleMatcherTest extends TestCase
{
    public function testMatchesSensitiveKeysCaseInsensitively(): void
    {
        $regexKeyRuleMatcher = new RegexKeyRuleMatcher(
            '/password|passwd|secret|token|api[_-]?key|authorization|cookie/i',
            SharedRuleFactory::fixedValue('[Filtered]'),
        );

        $this->assertTrue($regexKeyRuleMatcher->matches('accessToken'));
        $this->assertTrue($regexKeyRuleMatcher->matches('refresh_token'));
        $this->assertTrue($regexKeyRuleMatcher->matches('api-key'));
        $this->assertTrue($regexKeyRuleMatcher->matches('clientSecret'));
        $this->assertTrue($regexKeyRuleMatcher->matches('authorizationHeader'));
    }

    public function testDoesNotMatchNonSensitiveKeys(): void
    {
        $regexKeyRuleMatcher = new RegexKeyRuleMatcher('/token/i', SharedRuleFactory::fixedValue('[Filtered]'));

        $this->assertFalse($regexKeyRuleMatcher->matches('username'));
    }

    public function testMatchesIntegerKeysAsStrings(): void
    {
        $regexKeyRuleMatcher = new RegexKeyRuleMatcher('/^123$/', SharedRuleFactory::fixedValue('[Filtered]'));

        $this->assertTrue($regexKeyRuleMatcher->matches(123));
        $this->assertFalse($regexKeyRuleMatcher->matches(456));
    }

    public function testRejectsInvalidRegexPattern(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RegexKeyRuleMatcher('/unterminated', SharedRuleFactory::fixedValue('[Filtered]'));
    }
}
