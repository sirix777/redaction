<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Redactor;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\OffsetRule;
use stdClass;

final class RedactorObjectReflectionTest extends TestCase
{
    public function testClonedObjectIsProcessedAndOriginalUnchangedWithTopLevelRules(): void
    {
        $user = new stdClass();
        $user->username = 'carol';
        $user->password = 'mypass';

        $redactor = (new Redactor(
            [
                'password' => new OffsetRule(2),
            ],
            false
        ))
            ->setObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $result = $redactor->redact(['user' => $user]);

        $this->assertInstanceOf(stdClass::class, $result['user']);
        $this->assertNotSame($user, $result['user'], 'Should be a cloned instance');
        $this->assertSame('my****', $result['user']->password);
        $this->assertSame('carol', $result['user']->username);
        $this->assertSame('mypass', $user->password);
        $this->assertSame('carol', $user->username);
    }

    public function testClonedObjectIsProcessedWithNestedRules(): void
    {
        $user = new stdClass();
        $user->username = 'dave';
        $user->password = 'secr3t';

        $redactor = (new Redactor(
            [
                'password' => new OffsetRule(3),
            ],
            false
        ))
            ->setObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $result = $redactor->redact(['user' => $user]);

        $this->assertSame('sec***', $result['user']->password);
        $this->assertSame('dave', $result['user']->username);
        $this->assertSame('secr3t', $user->password);
    }

    public function testEmptyNonTraversableObjectsCompatibilityFlag(): void
    {
        $obj = new stdClass();
        $obj->secret = 'topsecret';

        $redactor = new Redactor([
            'secret' => new OffsetRule(2),
        ], false);
        $redactor->setObjectViewMode(ObjectViewModeEnum::Copy);

        $result = $redactor->redact(['obj' => $obj]);

        $this->assertIsObject($result['obj']);
        $this->assertSame('topsecret', $obj->secret);
        $this->assertSame('to*******', $result['obj']->secret);
    }

    public function testPrivatePropertiesAreProcessedViaReflection(): void
    {
        $user = new class('privpass', 'tok123') {
            public function __construct(private readonly string $password, private readonly string $token) {}

            public function getPassword(): string
            {
                return $this->password;
            }

            public function getToken(): string
            {
                return $this->token;
            }
        };

        $redactor = new Redactor([
            'password' => new OffsetRule(4),
            'token' => new OffsetRule(3),
        ], false);

        $redactor->setObjectViewMode(ObjectViewModeEnum::Copy);

        $result = $redactor->redact(['user' => $user]);
        $processed = $result['user'];

        $this->assertSame('priv****', $processed->password);
        $this->assertSame('tok***', $processed->token);
        $this->assertSame('privpass', $user->getPassword());
        $this->assertSame('tok123', $user->getToken());
        $this->assertNotSame($user, $processed);
    }
}
