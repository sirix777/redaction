<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactorInterface;

use function str_repeat;
use function strlen;

final class FullMaskRule implements RedactionRuleInterface
{
    public function apply(string $value, RedactorInterface $redactor): string
    {
        return str_repeat($redactor->getReplacement(), strlen($value));
    }
}
