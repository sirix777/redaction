<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\NullRule;
use Test\Sirix\Redaction\NestedArrayConversionTrait;

final class NullRuleTest extends TestCase
{
    use NestedArrayConversionTrait;

    public function testNullRule(): void
    {
        $rule = new NullRule();
        $redactor = new Redactor(['password' => $rule], false);
        $processed = $redactor->redact($this->convertNested(['password' => 'secret123']));

        $this->assertNull($processed['password']);
    }

    public function testNullRuleWithNestedData(): void
    {
        $redactor = (new Redactor(
            [
                'apiKey' => new NullRule(),
                'username' => new NullRule(),
            ],
            false
        ))
            ->setObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $processed = $redactor->redact($this->convertNested([
            'credentials' => [
                'apiKey' => 'abc123xyz',
                'username' => 'admin',
                'domain' => 'example.com',
            ],
        ]));

        $this->assertNull($processed['credentials']->apiKey);
        $this->assertNull($processed['credentials']->username);
        $this->assertSame('example.com', $processed['credentials']->domain);
    }
}
