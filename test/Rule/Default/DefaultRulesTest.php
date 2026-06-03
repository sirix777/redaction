<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule\Default;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\Default\DefaultRules;
use Sirix\Redaction\Rule\RedactionRuleInterface;

use function sprintf;

final class DefaultRulesTest extends TestCase
{
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

    public function testCriticalDefaultKeysArePresent(): void
    {
        $rules = DefaultRules::getAll();

        foreach (['password', 'card_number', 'cvv', 'expirydate', 'email', 'phone', 'name'] as $key) {
            $this->assertArrayHasKey($key, $rules);
        }
    }

    public function testDefaultRulesReturnFreshRuleInstances(): void
    {
        $first = DefaultRules::getAll();
        $second = DefaultRules::getAll();

        $this->assertEquals($first, $second);
        $this->assertNotSame($first['email'], $second['email']);
    }

    public function testDefaultRulesMaskCriticalKeys(): void
    {
        $redactor = new Redactor();

        $result = $redactor->redact([
            'password' => 'secret',
            'card_number' => '4111111111111111',
            'cvv' => '123',
            'expirydate' => '12/2030',
            'email' => 'john.doe@example.com',
            'phone' => '1234567890',
            'name' => 'John',
        ]);

        $this->assertSame('*', $result['password']);
        $this->assertSame('411111******1111', $result['card_number']);
        $this->assertSame('***', $result['cvv']);
        $this->assertSame('**/****', $result['expirydate']);
        $this->assertSame('joh****@example.com', $result['email']);
        $this->assertSame('1234****90', $result['phone']);
        $this->assertSame('Jo***n', $result['name']);
    }

    public function testUseDefaultRulesFalseDisablesDefaultRules(): void
    {
        $redactor = new Redactor(useDefaultRules: false);

        $payload = [
            'password' => 'secret',
            'email' => 'john.doe@example.com',
        ];

        $this->assertSame($payload, $redactor->redact($payload));
    }
}
