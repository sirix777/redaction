<?php

declare(strict_types=1);

namespace Sirix\Redaction;

use Sirix\Redaction\Enum\ObjectViewModeEnum;
use SplObjectStorage;

final class RedactionContext
{
    public int $currentDepth = 0;
    public int $nodesVisited = 0;
    public bool $totalNodeLimitExceeded = false;

    /**
     * @var null|SplObjectStorage<object, bool>
     */
    public ?SplObjectStorage $seenObjects;

    private function __construct(
        ObjectViewModeEnum $objectViewMode,
        public readonly RedactionRuleContextInterface $ruleContext,
    ) {
        $this->seenObjects = ObjectViewModeEnum::Skip === $objectViewMode
            ? null
            : new SplObjectStorage();
    }

    public static function forOptions(RedactorOptions $options): self
    {
        return new self(
            $options->objectViewMode,
            RedactionRuleContext::fromOptions($options),
        );
    }
}
