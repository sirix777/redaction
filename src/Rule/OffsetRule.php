<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactionRuleContextInterface;

use function abs;
use function intdiv;
use function max;
use function min;
use function sprintf;
use function str_repeat;
use function strlen;
use function substr;

final readonly class OffsetRule implements RedactionRuleInterface
{
    public function __construct(private int $offset) {}

    public function apply(string $value, RedactionRuleContextInterface $redactionRuleContext): string
    {
        $length = strlen($value);
        if (0 === $length) {
            return $value;
        }

        $limit   = $redactionRuleContext->getLengthLimit();
        $visible = $this->offset >= 0
            ? substr($value, 0, $this->offset)
            : substr($value, $this->offset);
        $hiddenLength = max(0, $length - abs($this->offset));
        $maxMaskBytes = null === $limit ? null : max(0, $limit - strlen($visible));
        $hidden       = $this->repeatMask($redactionRuleContext->getReplacement(), $hiddenLength, $maxMaskBytes);
        $placeholder  = sprintf($redactionRuleContext->getTemplate(), $hidden);

        $result = $this->offset >= 0
            ? $visible . $placeholder
            : $placeholder . $visible;

        if (null !== $limit) {
            return substr($result, 0, $limit);
        }

        return $result;
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
        $neededRepeats     = min($repeatCount, intdiv($maxBytes + $replacementLength - 1, $replacementLength));

        return substr(str_repeat($replacement, $neededRepeats), 0, $maxBytes);
    }
}
