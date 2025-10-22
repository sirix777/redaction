<?php

declare(strict_types=1);

namespace Sirix\Redaction\Enum;

enum ObjectViewModeEnum: string
{
    case Copy = 'copy';
    case PublicArray = 'public_array';
    case Skip = 'skip';
}
