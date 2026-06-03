<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Redactor;

use Closure;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\RedactionRuleContext;
use Sirix\Redaction\RedactionRuleContextInterface;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\OffsetRule;
use Sirix\Redaction\Rule\RedactionRuleInterface;
use stdClass;

use function method_exists;

final class RedactorLongRunningSafetyTest extends TestCase
{
    public function testRuleExceptionFailsClosedAndStateIsCleaned(): void
    {
        $events = [];
        $redactor = (new Redactor([
            'boom' => new class implements RedactionRuleInterface {
                public function apply(string $value, RedactionRuleContextInterface $context): ?string
                {
                    throw new RuntimeException('boom');
                }
            },
            'safe' => new OffsetRule(2),
        ], false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
            ->withOverflowPlaceholder('[REDACTION_ERROR]')
            ->withOnLimitExceededCallback(static function(array $info) use (&$events): void {
                $events[] = $info;
            })
        ;

        $this->assertSame('[REDACTION_ERROR]', $redactor->redact(['boom' => 'raw-secret'])['boom']);
        $this->assertSame('se****', $redactor->redact(['safe' => 'secret'])['safe']);
        $this->assertCount(1, $events);
        $this->assertSame('ruleException', $events[0]['type']);
        $this->assertSame('boom', $events[0]['key']);
    }

    public function testRuleExceptionWithNullPlaceholderReturnsNullNotRawValue(): void
    {
        $redactor = (new Redactor([
            'boom' => new class implements RedactionRuleInterface {
                public function apply(string $value, RedactionRuleContextInterface $context): ?string
                {
                    throw new RuntimeException('boom: ' . $value);
                }
            },
        ], false))
            ->withOverflowPlaceholder(null)
        ;

        $this->assertNull($redactor->redact(['boom' => 'raw-secret'])['boom']);
    }

    public function testLimitCallbackExceptionDoesNotAbortRedaction(): void
    {
        $redactor = (new Redactor([
            'password' => new OffsetRule(2),
        ], false))
            ->withMaxItemsPerContainer(1)
            ->withOnLimitExceededCallback(static function(): void {
                throw new RuntimeException('limit');
            })
        ;

        $this->assertSame(
            ['password' => 'se****', '__redaction_overflow__' => '...'],
            $redactor->redact(['password' => 'secret', 'raw' => 'raw-secret']),
        );
        $this->assertSame('se****', $redactor->redact(['password' => 'secret'])['password']);
    }

    public function testRuleReceivesMinimalContextWithoutRedactorServiceContract(): void
    {
        $rule = new class implements RedactionRuleInterface {
            public ?string $contextClass = null;
            public ?bool $contextIsRedactor = null;

            public function apply(string $value, RedactionRuleContextInterface $context): string
            {
                $this->contextClass = $context::class;
                $this->contextIsRedactor = method_exists($context, 'redact');

                return $context->getReplacement() . $context->getTemplate() . $context->getLengthLimit();
            }
        };

        $redactor = (new Redactor([
            'secret' => $rule,
        ], false))
            ->withReplacement('#')
            ->withTemplate('[%s]')
            ->withLengthLimit(8)
        ;

        $result = $redactor->redact(['secret' => 'value']);

        $this->assertSame('#[%s]8', $result['secret']);
        $this->assertSame(RedactionRuleContext::class, $rule->contextClass);
        $this->assertFalse($rule->contextIsRedactor);
    }

    public function testReentrantRedactCallDoesNotCorruptOuterTraversal(): void
    {
        $innerResult = null;
        $redactor = null;

        $reentrantRule = new class(static function() use (&$redactor, &$innerResult): void {
            self::assertInstanceOf(Redactor::class, $redactor);

            $innerResult = $redactor->redact([
                'secret' => 'inner-secret',
                'other' => 'other-secret',
            ]);
        }) implements RedactionRuleInterface {
            public function __construct(private readonly Closure $callback) {}

            public function apply(string $value, RedactionRuleContextInterface $context): string
            {
                ($this->callback)();

                return 'TRIGGERED';
            }
        };

        $redactor = (new Redactor([
            'trigger' => $reentrantRule,
            'secret' => new OffsetRule(2),
            'after' => new OffsetRule(2),
        ], false))
            ->withMaxTotalNodes(2)
            ->withOverflowPlaceholder('[NODE]')
        ;

        $outerResult = $redactor->redact([
            'trigger' => 'go',
            'after' => 'after-secret',
            'tail' => 'raw-secret',
        ]);

        $this->assertSame([
            'secret' => 'in**********',
            'other' => 'other-secret',
        ], $innerResult);
        $this->assertSame([
            'trigger' => 'TRIGGERED',
            'after' => 'af**********',
            'tail' => '[NODE]',
        ], $outerResult);
    }

    public function testRepeatedRedactCallsDoNotRetainSeenObjects(): void
    {
        $object = new stdClass();
        $object->self = $object;

        $redactor = (new Redactor([], false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
            ->withOverflowPlaceholder('[CYCLE]')
        ;

        $first = $redactor->redact($object);
        $second = $redactor->redact($object);

        $this->assertSame('[CYCLE]', $first->self);
        $this->assertSame('[CYCLE]', $second->self);
    }

    public function testSharedRedactorDoesNotCarryNodesVisitedBetweenCalls(): void
    {
        $redactor = (new Redactor([
            'secret' => new OffsetRule(2),
        ], false))
            ->withMaxTotalNodes(1)
        ;

        $this->assertSame('se****', $redactor->redact(['secret' => 'secret'])['secret']);
        $this->assertSame('se****', $redactor->redact(['secret' => 'secret'])['secret']);
    }

    public function testWithersDoNotMutateOriginalSharedInstance(): void
    {
        $shared = new Redactor([], false);
        $copyMode = $shared->withObjectViewMode(ObjectViewModeEnum::Copy);

        $this->assertSame(ObjectViewModeEnum::Skip, $shared->getObjectViewMode());
        $this->assertSame(ObjectViewModeEnum::Copy, $copyMode->getObjectViewMode());
    }
}
