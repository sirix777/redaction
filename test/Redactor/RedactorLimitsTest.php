<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Redactor;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\OffsetRule;
use stdClass;

final class RedactorLimitsTest extends TestCase
{
    public function testMaxDepthStopsTraversalAndUsesPlaceholder(): void
    {
        $redactor = (new Redactor([
            'password' => new OffsetRule(2),
        ], false))
            ->withMaxDepth(1)
            ->withOverflowPlaceholder('[DEPTH]')
        ;

        $result = $redactor->redact([
            'user' => [
                'password' => 'secret',
            ],
        ]);

        $this->assertSame([
            'user' => '[DEPTH]',
        ], $result);
    }

    public function testMaxDepthZeroReplacesTopLevelContainer(): void
    {
        $redactor = (new Redactor([], false))
            ->withMaxDepth(0)
            ->withOverflowPlaceholder('[DEPTH]')
        ;

        $this->assertSame('[DEPTH]', $redactor->redact([
            'password' => 'secret',
        ]));
    }

    public function testOverflowPlaceholderNullReplacesExceededBranchWithNull(): void
    {
        $redactor = (new Redactor([
            'password' => new OffsetRule(2),
        ], false))
            ->withMaxDepth(1)
            ->withOverflowPlaceholder(null)
        ;

        $result = $redactor->redact([
            'user' => [
                'password' => 'secret',
            ],
        ]);

        $this->assertSame([
            'user' => null,
        ], $result);
    }

    public function testOverflowPlaceholderNullReplacesExceededObjectBranchWithNull(): void
    {
        $user           = new stdClass();
        $user->password = 'raw-secret';

        $redactor = (new Redactor([
            'password' => new OffsetRule(2),
        ], false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
            ->withMaxDepth(1)
            ->withOverflowPlaceholder(null)
        ;

        $this->assertSame([
            'user' => null,
        ], $redactor->redact([
            'user' => $user,
        ]));
    }

    public function testMaxItemsPerContainerTruncatesAssociativeArray(): void
    {
        $redactor = (new Redactor([
            'password' => new OffsetRule(2),
            'token'    => new OffsetRule(2),
        ], false))
            ->withMaxItemsPerContainer(1)
            ->withOverflowPlaceholder('...')
        ;

        $result = $redactor->redact([
            'password' => 'secret',
            'token'    => 'abcdef',
        ]);

        $this->assertSame([
            'password'               => 'se****',
            '__redaction_overflow__' => '...',
        ], $result);
    }

    public function testMaxItemsPerContainerTruncatesListArray(): void
    {
        $redactor = (new Redactor([
            'password' => new OffsetRule(2),
        ], false))
            ->withMaxItemsPerContainer(1)
            ->withOverflowPlaceholder('...')
        ;

        $result = $redactor->redact([
            [
                'password' => 'secret',
            ],
            [
                'password' => 'hidden',
            ],
        ]);

        $this->assertSame([
            [
                'password' => 'se****',
            ],
            '...',
        ], $result);
    }

    public function testMaxItemsPerContainerZeroReturnsOnlyOverflowMarker(): void
    {
        $redactor = (new Redactor([], false))
            ->withMaxItemsPerContainer(0)
            ->withOverflowPlaceholder('...')
        ;

        $this->assertSame(['...'], $redactor->redact(['a', 'b']));
    }

    public function testMaxItemsPerContainerWithNullPlaceholderTruncatesWithoutMarker(): void
    {
        $redactor = (new Redactor([], false))
            ->withMaxItemsPerContainer(1)
            ->withOverflowPlaceholder(null)
        ;

        $this->assertSame([
            'a' => 1,
        ], $redactor->redact([
            'a' => 1,
            'b' => 2,
        ]));
    }

    public function testMaxTotalNodesStopsAfterLimit(): void
    {
        $redactor = (new Redactor([
            'password' => new OffsetRule(2),
            'token'    => new OffsetRule(2),
        ], false))
            ->withMaxTotalNodes(1)
            ->withOverflowPlaceholder('[NODE]')
        ;

        $result = $redactor->redact([
            'password' => 'secret',
            'token'    => 'abcdef',
        ]);

        $this->assertSame([
            'password' => 'se****',
            'token'    => '[NODE]',
        ], $result);
    }

    public function testMaxTotalNodesStopsIteratingAfterFirstExceededNode(): void
    {
        $events   = [];
        $redactor = (new Redactor([
            'password' => new OffsetRule(2),
            'token'    => new OffsetRule(2),
        ], false))
            ->withMaxTotalNodes(1)
            ->withOverflowPlaceholder('[NODE]')
            ->withOnLimitExceededCallback(static function(array $info) use (&$events): void {
                $events[] = $info;
            })
        ;

        $result = $redactor->redact([
            'password' => 'secret',
            'token'    => 'abcdef',
            'api_key'  => 'raw-secret',
        ]);

        $this->assertSame([
            'password'               => 'se****',
            'token'                  => '[NODE]',
            '__redaction_overflow__' => '[NODE]',
        ], $result);
        $this->assertCount(1, $events);
        $this->assertSame('maxTotalNodes', $events[0]['type']);
    }

    public function testMaxTotalNodesZeroReplacesFirstNode(): void
    {
        $redactor = (new Redactor([], false))
            ->withMaxTotalNodes(0)
            ->withOverflowPlaceholder('[NODE]')
        ;

        $this->assertSame([
            'a' => '[NODE]',
        ], $redactor->redact([
            'a' => 1,
        ]));
    }

    public function testMaxTotalNodesWithNullPlaceholderDoesNotKeepExceededRawValue(): void
    {
        $redactor = (new Redactor([
            'password' => new OffsetRule(2),
        ], false))
            ->withMaxTotalNodes(1)
            ->withOverflowPlaceholder(null)
        ;

        $this->assertSame(
            [
                'password' => 'se****',
                'token'    => null,
            ],
            $redactor->redact([
                'password' => 'secret',
                'token'    => 'raw-secret',
            ]),
        );
    }

    public function testLimitCallbackReceivesExpectedPayload(): void
    {
        $events   = [];
        $redactor = (new Redactor([], false))
            ->withMaxItemsPerContainer(1)
            ->withOnLimitExceededCallback(static function(array $info) use (&$events): void {
                $events[] = $info;
            })
        ;

        $redactor->redact([
            'a' => 1,
            'b' => 2,
        ]);

        $this->assertCount(1, $events);
        $this->assertSame('maxItemsPerContainer', $events[0]['type']);
        $this->assertSame('b', $events[0]['key']);
        $this->assertArrayHasKey('depth', $events[0]);
        $this->assertArrayHasKey('nodesVisited', $events[0]);
    }

    public function testLimitCallbackIsNotCalledWithoutLimitExceeded(): void
    {
        $called   = false;
        $redactor = (new Redactor([], false))
            ->withMaxItemsPerContainer(3)
            ->withOnLimitExceededCallback(static function() use (&$called): void {
                $called = true;
            })
        ;

        $redactor->redact([
            'a' => 1,
            'b' => 2,
        ]);

        $this->assertFalse($called);
    }

    public function testStateIsResetBetweenRedactCalls(): void
    {
        $redactor = (new Redactor([
            'password' => new OffsetRule(2),
        ], false))
            ->withMaxTotalNodes(1)
            ->withOverflowPlaceholder('[NODE]')
        ;

        $first = $redactor->redact([
            'password' => 'secret',
            'other'    => 'value',
        ]);
        $second = $redactor->redact([
            'password' => 'secret',
        ]);

        $this->assertSame('[NODE]', $first['other']);
        $this->assertSame('se****', $second['password']);
    }
}
