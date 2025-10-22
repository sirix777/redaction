<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactorInterface;

use function preg_replace;

final class EmailRule extends AbstractStartEndRule implements RedactionRuleInterface
{
    public function __construct()
    {
        parent::__construct(3, 4);
    }

    public function apply(string $value, RedactorInterface $redactor): string
    {
        $masked = preg_replace('/^([^@]{3})[^@]*(@.*)$/', '$1****$2', $value);

        if (null === $masked || $masked === $value) {
            return parent::apply($value, $redactor);
        }

        return $masked;
    }
}
