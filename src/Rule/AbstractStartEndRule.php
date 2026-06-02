<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use InvalidArgumentException;
use Sirix\Redaction\RedactionRuleContextInterface;

use function intdiv;
use function max;
use function min;
use function sprintf;
use function str_repeat;
use function strlen;
use function substr;

class AbstractStartEndRule
{
    private const DEFAULT_TEMPLATE = '%s';

    public function __construct(private readonly int $visibleStart, private readonly int $visibleEnd)
    {
        if ($visibleStart < 0 || $visibleEnd < 0) {
            throw new InvalidArgumentException('visibleStart and visibleEnd must be >= 0');
        }
    }

    public function apply(string $value, RedactionRuleContextInterface $context): string
    {
        $length = strlen($value);
        if (0 === $length) {
            return $value;
        }

        if ($length <= $this->visibleStart + $this->visibleEnd) {
            return substr($value, 0, 1)
                . $this->repeatMask($context->getReplacement(), $length - 1, $context->getLengthLimit());
        }

        $visibleStart = min($this->visibleStart, $length);
        $visibleEnd = min($this->visibleEnd, $length - $visibleStart);
        $hiddenLength = max(0, $length - $visibleStart - $visibleEnd);
        $prefix = substr($value, 0, $visibleStart);
        $isDefaultTemplate = self::DEFAULT_TEMPLATE === $context->getTemplate();
        $limit = $context->getLengthLimit();
        $maxMaskBytes = null === $limit ? null : max(0, $limit - strlen($prefix));

        $hidden = $this->repeatMask($context->getReplacement(), $hiddenLength, $maxMaskBytes);
        $placeholder = sprintf($context->getTemplate(), $hidden);

        $result = $prefix . $placeholder;

        if ($isDefaultTemplate && $visibleEnd > 0 && (null === $limit || strlen($result) < $limit)) {
            $result .= substr($value, -$visibleEnd);
        }

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
        $neededRepeats = min($repeatCount, intdiv($maxBytes + $replacementLength - 1, $replacementLength));

        return substr(str_repeat($replacement, $neededRepeats), 0, $maxBytes);
    }
}
