<?php

declare(strict_types=1);

namespace Sirix\Redaction;

use InvalidArgumentException;
use Sirix\Redaction\Enum\ObjectViewModeEnum;

use function is_callable;
use function preg_match;
use function substr_count;

final readonly class RedactorOptions
{
    public function __construct(
        public string $replacement = '*',
        public string $template = '%s',
        public ?int $lengthLimit = null,
        public ObjectViewModeEnum $objectViewMode = ObjectViewModeEnum::Skip,
        public ?int $maxDepth = null,
        public ?int $maxItemsPerContainer = null,
        public ?int $maxTotalNodes = null,
        public mixed $onLimitExceededCallback = null,
        public ?string $overflowPlaceholder = '...',
    ) {
        $this->assertSafeTemplate($template);
        $this->assertNullableNonNegativeInt($lengthLimit, 'lengthLimit');
        $this->assertNullableNonNegativeInt($maxDepth, 'maxDepth');
        $this->assertNullableNonNegativeInt($maxItemsPerContainer, 'maxItemsPerContainer');
        $this->assertNullableNonNegativeInt($maxTotalNodes, 'maxTotalNodes');

        if (null !== $onLimitExceededCallback && ! is_callable($onLimitExceededCallback)) {
            throw new InvalidArgumentException('onLimitExceededCallback must be null or callable');
        }
    }

    public function withReplacement(string $replacement): self
    {
        return new self(
            replacement: $replacement,
            template: $this->template,
            lengthLimit: $this->lengthLimit,
            objectViewMode: $this->objectViewMode,
            maxDepth: $this->maxDepth,
            maxItemsPerContainer: $this->maxItemsPerContainer,
            maxTotalNodes: $this->maxTotalNodes,
            onLimitExceededCallback: $this->onLimitExceededCallback,
            overflowPlaceholder: $this->overflowPlaceholder,
        );
    }

    public function withTemplate(string $template): self
    {
        return new self(
            replacement: $this->replacement,
            template: $template,
            lengthLimit: $this->lengthLimit,
            objectViewMode: $this->objectViewMode,
            maxDepth: $this->maxDepth,
            maxItemsPerContainer: $this->maxItemsPerContainer,
            maxTotalNodes: $this->maxTotalNodes,
            onLimitExceededCallback: $this->onLimitExceededCallback,
            overflowPlaceholder: $this->overflowPlaceholder,
        );
    }

    public function withLengthLimit(?int $lengthLimit): self
    {
        return new self(
            replacement: $this->replacement,
            template: $this->template,
            lengthLimit: $lengthLimit,
            objectViewMode: $this->objectViewMode,
            maxDepth: $this->maxDepth,
            maxItemsPerContainer: $this->maxItemsPerContainer,
            maxTotalNodes: $this->maxTotalNodes,
            onLimitExceededCallback: $this->onLimitExceededCallback,
            overflowPlaceholder: $this->overflowPlaceholder,
        );
    }

    public function withObjectViewMode(ObjectViewModeEnum $objectViewMode): self
    {
        return new self(
            replacement: $this->replacement,
            template: $this->template,
            lengthLimit: $this->lengthLimit,
            objectViewMode: $objectViewMode,
            maxDepth: $this->maxDepth,
            maxItemsPerContainer: $this->maxItemsPerContainer,
            maxTotalNodes: $this->maxTotalNodes,
            onLimitExceededCallback: $this->onLimitExceededCallback,
            overflowPlaceholder: $this->overflowPlaceholder,
        );
    }

    public function withMaxDepth(?int $maxDepth): self
    {
        return new self(
            replacement: $this->replacement,
            template: $this->template,
            lengthLimit: $this->lengthLimit,
            objectViewMode: $this->objectViewMode,
            maxDepth: $maxDepth,
            maxItemsPerContainer: $this->maxItemsPerContainer,
            maxTotalNodes: $this->maxTotalNodes,
            onLimitExceededCallback: $this->onLimitExceededCallback,
            overflowPlaceholder: $this->overflowPlaceholder,
        );
    }

    public function withMaxItemsPerContainer(?int $maxItemsPerContainer): self
    {
        return new self(
            replacement: $this->replacement,
            template: $this->template,
            lengthLimit: $this->lengthLimit,
            objectViewMode: $this->objectViewMode,
            maxDepth: $this->maxDepth,
            maxItemsPerContainer: $maxItemsPerContainer,
            maxTotalNodes: $this->maxTotalNodes,
            onLimitExceededCallback: $this->onLimitExceededCallback,
            overflowPlaceholder: $this->overflowPlaceholder,
        );
    }

    public function withMaxTotalNodes(?int $maxTotalNodes): self
    {
        return new self(
            replacement: $this->replacement,
            template: $this->template,
            lengthLimit: $this->lengthLimit,
            objectViewMode: $this->objectViewMode,
            maxDepth: $this->maxDepth,
            maxItemsPerContainer: $this->maxItemsPerContainer,
            maxTotalNodes: $maxTotalNodes,
            onLimitExceededCallback: $this->onLimitExceededCallback,
            overflowPlaceholder: $this->overflowPlaceholder,
        );
    }

    public function withOnLimitExceededCallback(?callable $onLimitExceededCallback): self
    {
        return new self(
            replacement: $this->replacement,
            template: $this->template,
            lengthLimit: $this->lengthLimit,
            objectViewMode: $this->objectViewMode,
            maxDepth: $this->maxDepth,
            maxItemsPerContainer: $this->maxItemsPerContainer,
            maxTotalNodes: $this->maxTotalNodes,
            onLimitExceededCallback: $onLimitExceededCallback,
            overflowPlaceholder: $this->overflowPlaceholder,
        );
    }

    public function onLimitExceededCallback(): ?callable
    {
        return $this->onLimitExceededCallback;
    }

    public function withOverflowPlaceholder(?string $overflowPlaceholder): self
    {
        return new self(
            replacement: $this->replacement,
            template: $this->template,
            lengthLimit: $this->lengthLimit,
            objectViewMode: $this->objectViewMode,
            maxDepth: $this->maxDepth,
            maxItemsPerContainer: $this->maxItemsPerContainer,
            maxTotalNodes: $this->maxTotalNodes,
            onLimitExceededCallback: $this->onLimitExceededCallback,
            overflowPlaceholder: $overflowPlaceholder,
        );
    }

    private function assertNullableNonNegativeInt(?int $value, string $name): void
    {
        if (null !== $value && $value < 0) {
            throw new InvalidArgumentException("{$name} must be null or >= 0");
        }
    }

    private function assertSafeTemplate(string $template): void
    {
        if (1 !== substr_count($template, '%s') || preg_match('/%(?!s)/', $template)) {
            throw new InvalidArgumentException('template must contain exactly one plain %s placeholder');
        }
    }
}
