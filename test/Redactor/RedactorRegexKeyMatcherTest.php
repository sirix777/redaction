<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Redactor;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\RedactionRuleContextInterface;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\Factory\SharedRuleFactory;
use Sirix\Redaction\Rule\RedactionRuleInterface;
use stdClass;

final class RedactorRegexKeyMatcherTest extends TestCase
{
    public function testRegexMatcherMasksNestedArrayKeys(): void
    {
        $redactor = new Redactor([
            SharedRuleFactory::regexKey(
                '/password|passwd|secret|token|api[_-]?key|authorization|cookie/i',
                SharedRuleFactory::fixedValue('[Filtered]'),
            ),
        ], false);

        $result = $redactor->redact([
            'user' => [
                'accessToken'   => 'access-token',
                'refresh_token' => 'refresh-token',
                'api-key'       => 'api-key-value',
                'clientSecret'  => 'secret-value',
                'name'          => 'Alice',
            ],
        ]);

        $this->assertSame([
            'user' => [
                'accessToken'   => '[Filtered]',
                'refresh_token' => '[Filtered]',
                'api-key'       => '[Filtered]',
                'clientSecret'  => '[Filtered]',
                'name'          => 'Alice',
            ],
        ], $result);
    }

    public function testRegexMatcherMasksObjectPropertiesInCopyMode(): void
    {
        $user                      = new stdClass();
        $user->authorizationHeader = 'Bearer token';
        $user->username            = 'alice';

        $redactor = (new Redactor([
            SharedRuleFactory::regexKey('/authorization/i', SharedRuleFactory::fixedValue('[Filtered]')),
        ], false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $result = $redactor->redact($user);

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame('[Filtered]', $result->authorizationHeader);
        $this->assertSame('alice', $result->username);
        $this->assertSame('Bearer token', $user->authorizationHeader);
    }

    public function testCustomExactRuleTakesPrecedenceOverRegexMatcher(): void
    {
        $redactor = new Redactor([
            'accessToken' => SharedRuleFactory::fixedValue('[Exact]'),
            SharedRuleFactory::regexKey('/token/i', SharedRuleFactory::fixedValue('[Regex]')),
        ], false);

        $result = $redactor->redact([
            'accessToken'  => 'access-token',
            'refreshToken' => 'refresh-token',
        ]);

        $this->assertSame('[Exact]', $result['accessToken']);
        $this->assertSame('[Regex]', $result['refreshToken']);
    }

    public function testCustomRegexMatcherTakesPrecedenceOverDefaultExactRule(): void
    {
        $redactor = new Redactor([
            SharedRuleFactory::regexKey('/password/i', SharedRuleFactory::fixedValue('[Regex]')),
        ]);

        $result = $redactor->redact([
            'password' => 'secret',
        ]);

        $this->assertSame('[Regex]', $result['password']);
    }

    public function testMatcherOrderIsDeterministicFirstMatchWins(): void
    {
        $redactor = new Redactor([
            SharedRuleFactory::regexKey('/token/i', SharedRuleFactory::fixedValue('[First]')),
            SharedRuleFactory::regexKey('/accessToken/i', SharedRuleFactory::fixedValue('[Second]')),
        ], false);

        $result = $redactor->redact([
            'accessToken' => 'access-token',
        ]);

        $this->assertSame('[First]', $result['accessToken']);
    }

    public function testRegexMatcherRespectsTraversalLimits(): void
    {
        $redactor = (new Redactor([
            SharedRuleFactory::regexKey('/token/i', SharedRuleFactory::fixedValue('[Filtered]')),
        ], false))
            ->withMaxDepth(1)
            ->withOverflowPlaceholder('[DEPTH]')
        ;

        $result = $redactor->redact([
            'user' => [
                'accessToken' => 'access-token',
            ],
        ]);

        $this->assertSame([
            'user' => '[DEPTH]',
        ], $result);
    }

    public function testRuleExceptionFromRegexMatcherFailsClosed(): void
    {
        $throwingRule = new class implements RedactionRuleInterface {
            public function apply(string $value, RedactionRuleContextInterface $redactionRuleContext): ?string
            {
                throw new RuntimeException('mask failed');
            }
        };

        $redactor = (new Redactor([
            SharedRuleFactory::regexKey('/token/i', $throwingRule),
        ], false))
            ->withOverflowPlaceholder('[ERROR]')
        ;

        $result = $redactor->redact([
            'accessToken' => 'access-token',
        ]);

        $this->assertSame('[ERROR]', $result['accessToken']);
    }
}
