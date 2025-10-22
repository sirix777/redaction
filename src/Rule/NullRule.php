<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactorInterface;

final class NullRule implements RedactionRuleInterface
{
    public function apply(string $value, RedactorInterface $redactor): ?string
    {
        return null;
    }
}
