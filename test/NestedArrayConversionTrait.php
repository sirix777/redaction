<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction;

use stdClass;

use function is_array;

trait NestedArrayConversionTrait
{
    private function convertNested(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->arrayToObjectRecursive($value);
            }
        }

        return $data;
    }

    private function arrayToObjectRecursive(array $data): object
    {
        $obj = new stdClass();
        foreach ($data as $key => $value) {
            $obj->{$key} = is_array($value) ? $this->arrayToObjectRecursive($value) : $value;
        }

        return $obj;
    }
}
