<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactionRuleContextInterface;

interface RedactionRuleInterface
{
    public function apply(string $value, RedactionRuleContextInterface $context): ?string;
}
