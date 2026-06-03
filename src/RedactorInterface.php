<?php

declare(strict_types=1);

namespace Sirix\Redaction;

interface RedactorInterface
{
    public function redact(mixed $rawData): mixed;
}
