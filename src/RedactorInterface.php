<?php

declare(strict_types=1);

namespace Sirix\Redaction;

use Sirix\Redaction\Enum\ObjectViewModeEnum;

interface RedactorInterface
{
    public function redact(mixed $rawData): mixed;

    public function setReplacement(string $replacement): RedactorInterface;

    public function getReplacement(): string;

    public function setTemplate(string $template): RedactorInterface;

    public function getTemplate(): string;

    public function setLengthLimit(?int $lengthLimit): RedactorInterface;

    public function getLengthLimit(): ?int;

    public function setObjectViewMode(ObjectViewModeEnum $mode): RedactorInterface;

    public function getObjectViewMode(): ObjectViewModeEnum;

    public function setMaxDepth(?int $depth): RedactorInterface;

    public function getMaxDepth(): ?int;

    public function setMaxItemsPerContainer(?int $count): RedactorInterface;

    public function getMaxItemsPerContainer(): ?int;

    public function setMaxTotalNodes(?int $count): RedactorInterface;

    public function getMaxTotalNodes(): ?int;

    public function setOnLimitExceededCallback(?callable $callback): RedactorInterface;

    public function getOnLimitExceededCallback(): ?callable;

    public function setOverflowPlaceholder(mixed $value): RedactorInterface;

    public function getOverflowPlaceholder(): mixed;
}
