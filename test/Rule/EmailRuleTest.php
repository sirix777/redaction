<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\EmailRule;
use Test\Sirix\Redaction\NestedArrayConversionTrait;

final class EmailRuleTest extends TestCase
{
    use NestedArrayConversionTrait;

    public function testEmailRedaction(): void
    {
        $emailRule = new EmailRule();
        $redactor  = new Redactor([
            'email' => $emailRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'email' => 'john.doe@example.com',
        ]));

        $this->assertSame('joh****@example.com', $processed['email']);
    }

    public function testShortEmailRedaction(): void
    {
        $emailRule = new EmailRule();
        $redactor  = new Redactor([
            'email' => $emailRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'email' => 'joe@example.com',
        ]));

        $this->assertSame('joe****@example.com', $processed['email']);
    }

    public function testEmailRedactionInNestedStructures(): void
    {
        $emailRule = new EmailRule();
        $redactor  = (new Redactor(
            [
                'email' => $emailRule,
            ],
            false
        ))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $processed = $redactor->redact($this->convertNested([
            'user' => [
                'contact' => [
                    'email' => 'alice.smith@company.org',
                ],
            ],
        ]));

        $this->assertSame('ali****@company.org', $processed['user']->contact->email);
    }

    public function testNonEmailValue(): void
    {
        $emailRule = new EmailRule();
        $redactor  = new Redactor([
            'value' => $emailRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'value' => 'This is not an email',
        ]));

        $this->assertSame('Thi*************mail', $processed['value']);
    }
}
