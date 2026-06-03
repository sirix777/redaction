<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Redactor;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\EmailRule;
use Sirix\Redaction\Rule\OffsetRule;
use stdClass;
use Test\Sirix\Redaction\NestedArrayConversionTrait;

final class RedactorNestedKeysTest extends TestCase
{
    use NestedArrayConversionTrait;

    public function testRuleAppliedOnlyToScalarValuesNotArraysWithSameKey(): void
    {
        $redactor = (new Redactor(
            [
                'user' => new EmailRule(),
                'name' => new OffsetRule(1),
                'phone' => new OffsetRule(5),
            ],
            false
        ))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $data = [
            'card_number' => '1234567890123456',
            'user' => [
                'user' => 'john.doe@example.com',
                'name' => 'John Doe',
                'phone' => '+44123456789012',
            ],
        ];

        $processed = $redactor->redact($this->convertNested($data));

        /** @var stdClass $user */
        $user = $processed['user'];

        $this->assertSame('joh****@example.com', $user->user);
        $this->assertSame('J*******', $user->name);
        $this->assertSame('+4412**********', $user->phone);
        $this->assertSame('1234567890123456', $processed['card_number']);
    }

    public function testMultipleLevelsOfNestedSameKeys(): void
    {
        $redactor = (new Redactor(
            [
                'data' => new OffsetRule(2),
                'info' => new OffsetRule(3),
            ],
            false
        ))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $data = [
            'data' => [
                'info' => [
                    'data' => 'secret123',
                    'info' => 'confidential456',
                ],
                'data' => 'another_secret',
            ],
        ];

        $processed = $redactor->redact($this->convertNested($data));

        /** @var stdClass $dataData */
        $dataData = $processed['data'];

        /** @var stdClass $infoData */
        $infoData = $dataData->info;
        $this->assertSame('se*******', $infoData->data);
        $this->assertSame('con************', $infoData->info);
        $this->assertSame('an************', $dataData->data);
    }

    public function testArraysAndObjectsMixedWithSameKeys(): void
    {
        $redactor = (new Redactor(
            [
                'token' => new OffsetRule(4),
            ],
            false
        ))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $data = [
            'token' => [
                'auth' => [
                    'token' => 'bearer_token_123',  // Строка
                ],
                'refresh' => [
                    'token' => 'refresh_token_456',  // Строка
                ],
            ],
        ];

        $processed = $redactor->redact($this->convertNested($data));

        /** @var stdClass $token */
        $token = $processed['token'];

        /** @var stdClass $auth */
        $auth = $token->auth;

        /** @var stdClass $refresh */
        $refresh = $token->refresh;
        $this->assertSame('bear************', $auth->token);
        $this->assertSame('refr*************', $refresh->token);
    }

    public function testRuleNotAppliedToNonScalarValues(): void
    {
        $redactor = new Redactor([
            'config' => new OffsetRule(2),
        ], false);

        $data = [
            'config' => ['setting1' => 'value1'],
            'other' => [
                'config' => 'secret_config_value',
            ],
        ];

        $processed = $redactor->redact($data);

        $this->assertIsArray($processed['config']);
        $this->assertSame('value1', $processed['config']['setting1']);
        $this->assertSame('se*****************', $processed['other']['config']);
    }
}
