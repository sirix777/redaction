<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactorInterface;

use function preg_replace;

final class NameRule extends AbstractStartEndRule implements RedactionRuleInterface
{
    public function __construct()
    {
        parent::__construct(2, 2);
    }

    public function apply(string $value, RedactorInterface $redactor): string
    {
        $masked = preg_replace('/\b(\w{2})\w*(\w)\b/', '$1***$2', $value);

        if (null === $masked || $masked === $value) {
            return parent::apply($value, $redactor);
        }

        return $masked;
    }
}
