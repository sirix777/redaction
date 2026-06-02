<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Redactor;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorOptions;
use Sirix\Redaction\Rule\OffsetRule;

final class RedactorOptionsTest extends TestCase
{
    public function testCanConfigureRedactorThroughOptions(): void
    {
        $redactor = new Redactor(
            customRules: [
                'password' => new OffsetRule(2),
            ],
            useDefaultRules: false,
            options: new RedactorOptions(
                replacement: '#',
                lengthLimit: 5,
                objectViewMode: ObjectViewModeEnum::Copy,
            ),
        );

        $this->assertSame('#', $redactor->getReplacement());
        $this->assertSame(5, $redactor->getLengthLimit());
        $this->assertSame(ObjectViewModeEnum::Copy, $redactor->getObjectViewMode());
        $this->assertSame('se###', $redactor->redact(['password' => 'secret'])['password']);
    }

    public function testRejectsInvalidConstructorRule(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Redactor(['password' => 'not-a-rule']);
    }

    public function testWithersReturnNewOptionsInstance(): void
    {
        $options = new RedactorOptions();
        $changed = $options->withMaxDepth(2);

        $this->assertNull($options->maxDepth);
        $this->assertSame(2, $changed->maxDepth);
    }

    public function testRejectsUnsafeTemplate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RedactorOptions(template: '%100s');
    }

    public function testRejectsNegativeLimit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RedactorOptions(maxDepth: -1);
    }

    public function testRejectsNonCallableCallback(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new RedactorOptions(onLimitExceededCallback: 'not-a-callable-name-123');
    }
}
