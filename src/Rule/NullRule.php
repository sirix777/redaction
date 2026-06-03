<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactionRuleContextInterface;

final class NullRule implements RedactionRuleInterface
{
    public function apply(string $value, RedactionRuleContextInterface $redactionRuleContext): ?string
    {
        return null;
    }
}
