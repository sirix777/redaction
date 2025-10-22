<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactorInterface;

interface RedactionRuleInterface
{
    public function apply(string $value, RedactorInterface $redactor): ?string;
}
