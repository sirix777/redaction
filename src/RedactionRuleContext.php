<?php

declare(strict_types=1);

namespace Sirix\Redaction;

final readonly class RedactionRuleContext implements RedactionRuleContextInterface
{
    public function __construct(private string $replacement, private string $template, private ?int $lengthLimit) {}

    public static function fromOptions(RedactorOptions $options): self
    {
        return new self(
            replacement: $options->replacement,
            template: $options->template,
            lengthLimit: $options->lengthLimit,
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
