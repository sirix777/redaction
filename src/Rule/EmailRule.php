<?php

declare(strict_types=1);

namespace Sirix\Redaction\Rule;

use Sirix\Redaction\RedactionRuleContextInterface;

use function max;
use function strpos;
use function substr;

final class EmailRule extends AbstractStartEndRule implements RedactionRuleInterface
{
    public function __construct()
    {
        parent::__construct(3, 4);
    }

    public function apply(string $value, RedactionRuleContextInterface $redactionRuleContext): string
    {
        $atPosition = strpos($value, '@');
        if (false === $atPosition || $atPosition < 3) {
            return parent::apply($value, $redactionRuleContext);
        }

        $maskedPrefix = substr($value, 0, 3) . '****';
        $limit = $redactionRuleContext->getLengthLimit();
        if (null === $limit) {
            return $maskedPrefix . substr($value, $atPosition);
        }

        return substr($maskedPrefix . substr($value, $atPosition, max(0, $limit - 7)), 0, $limit);
    }
}
