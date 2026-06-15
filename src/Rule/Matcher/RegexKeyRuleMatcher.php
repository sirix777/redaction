<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule\Matcher;

use InvalidArgumentException;
use Sirix\Redaction\Rule\RedactionRuleInterface;

use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;

final readonly class RegexKeyRuleMatcher implements KeyRuleMatcherInterface
{
    public function __construct(private string $pattern, private RedactionRuleInterface $redactionRule)
    {
        $this->assertValidPattern();
    }

    public function matches(int|string $key): bool
    {
        return 1 === preg_match($this->pattern, (string) $key);
    }

    public function rule(): RedactionRuleInterface
    {
        return $this->redactionRule;
    }

    private function assertValidPattern(): void
    {
        set_error_handler(static fn (): bool => true);

        try {
            $result = preg_match($this->pattern, '');
        } finally {
            restore_error_handler();
        }

        if (false === $result) {
            throw new InvalidArgumentException(sprintf('Invalid regex pattern "%s"', $this->pattern));
        }
    }
}
