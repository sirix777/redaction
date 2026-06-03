<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use InvalidArgumentException;
use LogicException;
use Sirix\Redaction\RedactionRuleContextInterface;

use function function_exists;
use function grapheme_strlen;
use function grapheme_substr;
use function intdiv;
use function is_int;
use function max;
use function min;
use function sprintf;
use function str_repeat;

final readonly class UnicodeStartEndRule implements RedactionRuleInterface
{
    private const DEFAULT_TEMPLATE = '%s';

    public function __construct(private int $visibleStart, private int $visibleEnd)
    {
        if ($visibleStart < 0 || $visibleEnd < 0) {
            throw new InvalidArgumentException('visibleStart and visibleEnd must be >= 0');
        }
    }

    public function apply(string $value, RedactionRuleContextInterface $redactionRuleContext): string
    {
        $this->assertIntlAvailable();

        $length = $this->length($value);
        if (0 === $length) {
            return $value;
        }

        if ($length <= $this->visibleStart + $this->visibleEnd) {
            return $this->substring($value, 0, 1)
                . $this->repeatMask(
                    $redactionRuleContext->getReplacement(),
                    $length - 1,
                    $redactionRuleContext->getLengthLimit()
                );
        }

        $visibleStart = min($this->visibleStart, $length);
        $visibleEnd = min($this->visibleEnd, $length - $visibleStart);
        $hiddenLength = max(0, $length - $visibleStart - $visibleEnd);
        $prefix = $this->substring($value, 0, $visibleStart);
        $limit = $redactionRuleContext->getLengthLimit();
        $maxMaskCharacters = null === $limit ? null : max(0, $limit - $this->length($prefix));
        $hidden = $this->repeatMask($redactionRuleContext->getReplacement(), $hiddenLength, $maxMaskCharacters);
        $placeholder = sprintf($redactionRuleContext->getTemplate(), $hidden);

        $result = $prefix . $placeholder;

        if (self::DEFAULT_TEMPLATE === $redactionRuleContext->getTemplate()
            && $visibleEnd > 0
            && (null === $limit || $this->length($result) < $limit)
        ) {
            $result .= $this->substring($value, -$visibleEnd);
        }

        return $this->truncate($result, $limit);
    }

    private function assertIntlAvailable(): void
    {
        if (! function_exists('grapheme_strlen') || ! function_exists('grapheme_substr')) {
            throw new LogicException('UnicodeStartEndRule requires the intl PHP extension.');
        }
    }

    private function length(string $value): int
    {
        $length = grapheme_strlen($value);
        if (! is_int($length)) {
            throw new InvalidArgumentException('UnicodeStartEndRule expects valid UTF-8 input.');
        }

        return $length;
    }

    private function substring(string $value, int $offset, ?int $length = null): string
    {
        $substring = null === $length
            ? grapheme_substr($value, $offset)
            : grapheme_substr($value, $offset, $length);

        if (false === $substring) {
            throw new InvalidArgumentException('UnicodeStartEndRule expects valid UTF-8 input.');
        }

        return $substring;
    }

    private function truncate(string $value, ?int $maxCharacters): string
    {
        if (null === $maxCharacters) {
            return $value;
        }

        if ($maxCharacters <= 0) {
            return '';
        }

        return $this->substring($value, 0, $maxCharacters);
    }

    private function repeatMask(string $replacement, int $repeatCount, ?int $maxCharacters = null): string
    {
        if ($repeatCount <= 0 || '' === $replacement || 0 === $maxCharacters) {
            return '';
        }

        if (null === $maxCharacters) {
            return str_repeat($replacement, $repeatCount);
        }

        $replacementLength = $this->length($replacement);
        $neededRepeats = min($repeatCount, intdiv($maxCharacters + $replacementLength - 1, $replacementLength));

        return $this->truncate(str_repeat($replacement, $neededRepeats), $maxCharacters);
    }
}
