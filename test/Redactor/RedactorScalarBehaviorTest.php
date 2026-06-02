<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Redactor;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\FullMaskRule;

final class RedactorScalarBehaviorTest extends TestCase
{
    public function testTopLevelScalarIsReturnedUnchanged(): void
    {
        $redactor = new Redactor([
            'secret' => new FullMaskRule(),
        ], false);

        $this->assertSame('secret', $redactor->redact('secret'));
    }
}
