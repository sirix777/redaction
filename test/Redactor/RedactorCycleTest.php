<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Redactor;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use stdClass;

final class RedactorCycleTest extends TestCase
{
    public function testObjectCycleIsDetectedInCopyMode(): void
    {
        $a = new stdClass();
        $b = new stdClass();
        $a->child = $b;
        $b->parent = $a;

        $redactor = (new Redactor([], false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
            ->withOverflowPlaceholder('[CYCLE]')
        ;

        $result = $redactor->redact($a);

        $this->assertInstanceOf(stdClass::class, $result);
        $this->assertSame('[CYCLE]', $result->child->parent);
    }

    public function testObjectCycleInvokesLimitCallback(): void
    {
        $events = [];
        $a = new stdClass();
        $a->self = $a;

        $redactor = (new Redactor([], false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
            ->withOnLimitExceededCallback(static function(array $info) use (&$events): void {
                $events[] = $info;
            })
        ;

        $redactor->redact($a);

        $this->assertCount(1, $events);
        $this->assertSame('cycle', $events[0]['type']);
        $this->assertSame(stdClass::class, $events[0]['class']);
    }

    public function testObjectCycleWithNullPlaceholderReturnsNullInsteadOfRawObject(): void
    {
        $a = new stdClass();
        $a->self = $a;

        $redactor = (new Redactor([], false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
            ->withOverflowPlaceholder(null)
        ;

        $result = $redactor->redact($a);

        $this->assertNull($result->self);
    }

    public function testArraySelfReferenceIsStoppedByMaxDepth(): void
    {
        $data = [];
        $data['self'] = &$data;

        $redactor = (new Redactor([], false))
            ->withMaxDepth(2)
            ->withOverflowPlaceholder('[DEPTH]')
        ;

        $result = $redactor->redact($data);

        $this->assertSame('[DEPTH]', $result['self']['self']);
    }
}
