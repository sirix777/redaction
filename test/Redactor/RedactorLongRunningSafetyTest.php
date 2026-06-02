<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Redactor;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\RedactionRuleContextInterface;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\OffsetRule;
use Sirix\Redaction\Rule\RedactionRuleInterface;
use stdClass;

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

            public function apply(string $value, RedactionRuleContextInterface $context): string
            {
                $this->contextClass = $context::class;

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
        $this->assertSame(Redactor::class, $rule->contextClass);
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
