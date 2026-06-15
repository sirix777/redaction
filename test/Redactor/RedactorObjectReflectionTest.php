<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Redactor;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\OffsetRule;
use stdClass;

enum RedactorObjectReflectionTestEnum
{
    case Active;
}

final class RedactorObjectReflectionTest extends TestCase
{
    public function testClonedObjectIsProcessedAndOriginalUnchangedWithTopLevelRules(): void
    {
        $user           = new stdClass();
        $user->username = 'carol';
        $user->password = 'mypass';

        $redactor = (new Redactor(
            [
                'password' => new OffsetRule(2),
            ],
            false
        ))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $result = $redactor->redact([
            'user' => $user,
        ]);

        /** @var stdClass $resultUser */
        $resultUser = $result['user'];

        $this->assertInstanceOf(stdClass::class, $result['user']);
        $this->assertNotSame($user, $result['user'], 'Should be a cloned instance');
        $this->assertSame('my****', $resultUser->password);
        $this->assertSame('carol', $resultUser->username);
        $this->assertSame('mypass', $user->password);
        $this->assertSame('carol', $user->username);
    }

    public function testClonedObjectIsProcessedWithNestedRules(): void
    {
        $user           = new stdClass();
        $user->username = 'dave';
        $user->password = 'secr3t';

        $redactor = (new Redactor(
            [
                'password' => new OffsetRule(3),
            ],
            false
        ))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $result = $redactor->redact([
            'user' => $user,
        ]);

        /** @var stdClass $resultUser */
        $resultUser = $result['user'];

        $this->assertSame('sec***', $resultUser->password);
        $this->assertSame('dave', $resultUser->username);
        $this->assertSame('secr3t', $user->password);
    }

    public function testEmptyNonTraversableObjectsCompatibilityFlag(): void
    {
        $obj         = new stdClass();
        $obj->secret = 'topsecret';

        $redactor = (new Redactor([
            'secret' => new OffsetRule(2),
        ], false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $result = $redactor->redact([
            'obj' => $obj,
        ]);

        /** @var stdClass $resultObj */
        $resultObj = $result['obj'];

        $this->assertIsObject($result['obj']);
        $this->assertSame('topsecret', $obj->secret);
        $this->assertSame('to*******', $resultObj->secret);
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

        $redactor = (new Redactor([
            'password' => new OffsetRule(4),
            'token'    => new OffsetRule(3),
        ], false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $result = $redactor->redact([
            'user' => $user,
        ]);

        /** @var stdClass $processed */
        $processed = $result['user'];

        $this->assertSame('priv****', $processed->password);
        $this->assertSame('tok***', $processed->token);
        $this->assertSame('privpass', $user->getPassword());
        $this->assertSame('tok123', $user->getToken());
        $this->assertNotSame($user, $processed);
    }

    public function testCopyModeAlwaysReturnsPlainObjectCopy(): void
    {
        $user       = new stdClass();
        $user->name = 'public';

        $redactor = (new Redactor([], false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $result = $redactor->redact($user);

        /** @var stdClass $resultObj */
        $resultObj = $result;

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertNotSame($user, $result);
        $this->assertSame('public', $resultObj->name);
    }

    public function testPublicArrayModeProcessesOnlyPublicProperties(): void
    {
        $user = new class('privpass') {
            public string $password = 'publicpass';

            public function __construct(private readonly string $token) {}

            public function getToken(): string
            {
                return $this->token;
            }
        };

        $redactor = (new Redactor([
            'password' => new OffsetRule(3),
            'token'    => new OffsetRule(3),
        ], false))
            ->withObjectViewMode(ObjectViewModeEnum::PublicArray)
        ;

        $result = $redactor->redact($user);

        $this->assertSame([
            'password' => 'pub*******',
        ], $result);
        $this->assertSame('privpass', $user->getToken());
    }

    public function testSkipModeDoesNotTraverseObjectProperties(): void
    {
        $user           = new stdClass();
        $user->password = 'secret';

        $redactor = new Redactor([
            'password' => new OffsetRule(2),
        ], false);

        $this->assertSame('[object stdClass]', $redactor->redact($user));
    }

    public function testUnitEnumIsReturnedUnchanged(): void
    {
        $redactor = (new Redactor([], false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $this->assertSame(
            RedactorObjectReflectionTestEnum::Active,
            $redactor->redact(RedactorObjectReflectionTestEnum::Active),
        );
    }
}
