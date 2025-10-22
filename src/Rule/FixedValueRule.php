<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactorInterface;

final class FixedValueRule implements RedactionRuleInterface
{
    public function __construct(private readonly string $value) {}

    public function apply(string $value, RedactorInterface $redactor): string
    {
        return $this->value;
    }
}
