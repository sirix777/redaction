<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\PhoneRule;
use Test\Sirix\Redaction\NestedArrayConversionTrait;

final class PhoneRuleTest extends TestCase
{
    use NestedArrayConversionTrait;

    public function testPhoneNumberRedaction(): void
    {
        $phoneRule = new PhoneRule();
        $redactor  = new Redactor([
            'phone' => $phoneRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'phone' => '1234567890',
        ]));

        $this->assertSame('1234****90', $processed['phone']);
    }

    public function testInternationalPhoneNumberRedaction(): void
    {
        $phoneRule = new PhoneRule();
        $redactor  = new Redactor([
            'phone' => $phoneRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'phone' => '+44123456789012',
        ]));

        $this->assertSame('+4412****12', $processed['phone']);
    }

    public function testFormattedPhoneNumberRedaction(): void
    {
        $phoneRule = new PhoneRule();
        $redactor  = new Redactor([
            'phone' => $phoneRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'phone' => '123-456-7890',
        ]));

        $this->assertSame('123-******90', $processed['phone']);
    }

    public function testPhoneRedactionInNestedStructures(): void
    {
        $phoneRule = new PhoneRule();
        $redactor  = (new Redactor(
            [
                'phone' => $phoneRule,
            ],
            false
        ))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $processed = $redactor->redact($this->convertNested([
            'user' => [
                'contact' => [
                    'phone' => '9876543210',
                ],
            ],
        ]));

        $this->assertSame('9876****10', $processed['user']->contact->phone);
    }

    public function testNonPhoneValue(): void
    {
        $phoneRule = new PhoneRule();
        $redactor  = new Redactor([
            'value' => $phoneRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'value' => 'This is not a phone number',
        ]));

        $this->assertSame('This********************er', $processed['value']);
    }

    public function testShortPhoneNumber(): void
    {
        $phoneRule = new PhoneRule();
        $redactor  = new Redactor([
            'phone' => $phoneRule,
        ], false);
        $processed = $redactor->redact($this->convertNested([
            'phone' => '12345',
        ]));

        $this->assertSame('1****', $processed['phone']);
    }
}
