<?php

declare(strict_types=1);

namespace Sirix\Redaction;

interface RedactionRuleContextInterface
{
    public function getReplacement(): string;

    public function getTemplate(): string;

    public function getLengthLimit(): ?int;
}
