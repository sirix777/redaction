<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactorInterface;

use function abs;
use function max;
use function sprintf;
use function str_repeat;
use function strlen;
use function substr;

final class OffsetRule implements RedactionRuleInterface
{
    public function __construct(private readonly int $offset) {}

    public function apply(string $value, RedactorInterface $redactor): string
    {
        $length = strlen($value);
        if (0 === $length) {
            return $value;
        }

        $hiddenLength = max(0, $length - abs($this->offset));
        $hidden = str_repeat($redactor->getReplacement(), $hiddenLength);
        $placeholder = sprintf($redactor->getTemplate(), $hidden);

        $result = $this->offset >= 0
            ? substr($value, 0, $this->offset) . $placeholder
            : $placeholder . substr($value, $this->offset);

        if (null !== $redactor->getLengthLimit()) {
            return substr($result, 0, $redactor->getLengthLimit());
        }

        return $result;
    }
}
