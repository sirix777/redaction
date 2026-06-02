<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactionRuleContextInterface;

use function preg_replace;
use function substr;

final class PhoneRule extends AbstractStartEndRule implements RedactionRuleInterface
{
    public function __construct()
    {
        parent::__construct(4, 2);
    }

    public function apply(string $value, RedactionRuleContextInterface $context): string
    {
        $masked = preg_replace('/(\d{4})\d*(\d{2})/', '$1****$2', $value);

        if (null === $masked || $masked === $value) {
            return parent::apply($value, $context);
        }

        $limit = $context->getLengthLimit();
        if (null !== $limit) {
            return substr($masked, 0, $limit);
        }

        return $masked;
    }
}
