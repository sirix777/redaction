<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Redactor;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorInterface;
use Sirix\Redaction\Rule\RedactionRuleInterface;

final class RedactorCustomRulesOverrideTest extends TestCase
{
    public function testCustomRulesOverrideDefaultsWhenKeysOverlap(): void
    {
        $customEmailRule = new class implements RedactionRuleInterface {
            public function apply(string $value, RedactorInterface $redactor): string
            {
                return 'CUSTOM_EMAIL_MASK';
            }
        };

        $redactor = new Redactor([
            'email' => $customEmailRule,
        ]);

        $input = [
            'email' => 'john.doe@example.com',
            'phone' => '1234567890',
        ];

        $result = $redactor->redact($input);

        $this->assertSame('CUSTOM_EMAIL_MASK', $result['email']);
        $this->assertSame('1234****90', $result['phone']);
    }
}
