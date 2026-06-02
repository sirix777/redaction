<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactionRuleContextInterface;

final readonly class FixedValueRule implements RedactionRuleInterface
{
    public function __construct(private string $value) {}

    public function apply(string $value, RedactionRuleContextInterface $context): string
    {
        return $this->value;
    }
}
