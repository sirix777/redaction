<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule\Matcher;

use Sirix\Redaction\Rule\RedactionRuleInterface;

interface KeyRuleMatcherInterface
{
    public function matches(int|string $key): bool;

    public function rule(): RedactionRuleInterface;
}
