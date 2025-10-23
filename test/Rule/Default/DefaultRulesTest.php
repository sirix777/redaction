<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule\Default;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Rule\Default\DefaultRules;
use Sirix\Redaction\Rule\Factory\SharedRuleFactory;
use Sirix\Redaction\Rule\RedactionRuleInterface;

use function array_key_first;
use function sprintf;

final class DefaultRulesTest extends TestCase
{
    public function setUp(): void
    {
        SharedRuleFactory::clearCache();
    }

    public function testRulesAreCached(): void
    {
        $first = DefaultRules::getAll();
        $second = DefaultRules::getAll();

        foreach ($first as $key => $rule) {
            $this->assertArrayHasKey($key, $second);
            $this->assertSame(
                $rule,
                $second[$key],
                sprintf('Rule instance for key "%s" should be cached and identical', (string) $key)
            );
        }
    }

    public function testClearCacheWorks(): void
    {
        $before = DefaultRules::getAll();
        $key = array_key_first($before);
        $this->assertNotNull($key);

        $firstInstance = $before[$key];
        DefaultRules::clearCache();

        $after = DefaultRules::getAll();
        $this->assertArrayHasKey($key, $after);
        $this->assertNotSame(
            $firstInstance,
            $after[$key],
            'After clearCache new rule instances should be created'
        );
    }

    public function testSameInstancesReturned(): void
    {
        $a = DefaultRules::getAll();
        $b = DefaultRules::getAll();

        $keys = ['email', 'phone', 'card_number', 'cvv'];
        foreach ($keys as $key) {
            if (! isset($a[$key])) {
                // If some key does not exist in defaults, skip assertion for it
                continue;
            }
            $this->assertSame(
                $a[$key],
                $b[$key],
                sprintf('Instances for key "%s" should be the same between calls', $key)
            );
        }
    }

    public function testAllRulesAreRedactionRuleInterface(): void
    {
        $rules = DefaultRules::getAll();
        $this->assertNotEmpty($rules);

        foreach ($rules as $key => $rule) {
            $this->assertInstanceOf(
                RedactionRuleInterface::class,
                $rule,
                sprintf('Rule for key "%s" must implement RedactionRuleInterface', (string) $key)
            );
        }
    }
}
