<?php

declare(strict_types=1);

namespace Sirix\Redaction;

final readonly class RedactionRuleContext implements RedactionRuleContextInterface
{
    public function __construct(private string $replacement, private string $template, private ?int $lengthLimit) {}

    public static function fromOptions(RedactorOptions $redactorOptions): self
    {
        return new self(
            replacement: $redactorOptions->replacement,
            template: $redactorOptions->template,
            lengthLimit: $redactorOptions->lengthLimit,
        );
    }

    public function getReplacement(): string
    {
        return $this->replacement;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getLengthLimit(): ?int
    {
        return $this->lengthLimit;
    }
}
