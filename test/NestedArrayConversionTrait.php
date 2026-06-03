<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction;

use stdClass;

use function is_array;

trait NestedArrayConversionTrait
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function convertNested(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->arrayToObjectRecursive($value);
            }
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function arrayToObjectRecursive(array $data): object
    {
        $obj = new stdClass();
        foreach ($data as $key => $value) {
            $obj->{$key} = is_array($value) ? $this->arrayToObjectRecursive($value) : $value;
        }

        return $obj;
    }
}
