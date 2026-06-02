<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactionRuleContextInterface;

use function intdiv;
use function min;
use function str_repeat;
use function strlen;
use function substr;

final class FullMaskRule implements RedactionRuleInterface
{
    public function apply(string $value, RedactionRuleContextInterface $context): string
    {
        $length = strlen($value);
        $limit = $context->getLengthLimit();

        return $this->repeatMask($context->getReplacement(), $length, $limit);
    }

    private function repeatMask(string $replacement, int $repeatCount, ?int $maxBytes = null): string
    {
        if ($repeatCount <= 0 || '' === $replacement || 0 === $maxBytes) {
            return '';
        }

        if (null === $maxBytes) {
            return str_repeat($replacement, $repeatCount);
        }

        $replacementLength = strlen($replacement);
        $neededRepeats = min($repeatCount, intdiv($maxBytes + $replacementLength - 1, $replacementLength));

        return substr(str_repeat($replacement, $neededRepeats), 0, $maxBytes);
    }
}
