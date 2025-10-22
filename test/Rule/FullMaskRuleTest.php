<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Rule;

use PHPUnit\Framework\TestCase;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\FullMaskRule;
use stdClass;

use function array_keys;
use function array_map;
use function count;
use function is_array;
use function range;
use function str_repeat;
use function strlen;

final class FullMaskRuleTest extends TestCase
{
    public function testFullMaskRule(): void
    {
        $rule = new FullMaskRule();
        $redactor = new Redactor(['secret' => $rule], false);
        $processed = $redactor->redact($this->convertNested(['secret' => 'my_secret_value']));

        $expected = str_repeat('*', strlen('my_secret_value'));
        $this->assertSame($expected, $processed['secret']);
    }

    public function testCustomReplacement(): void
    {
        $rule = new FullMaskRule();
        $redactor = new Redactor(['secret' => $rule], false);
        $redactor->setReplacement('#');

        $processed = $redactor->redact($this->convertNested(['secret' => 'my_secret_value']));

        $expected = str_repeat('#', strlen('my_secret_value'));
        $this->assertSame($expected, $processed['secret']);
    }

    private function convertNested(array $data): array
    {
        foreach ($data as &$value) {
            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    $obj = new stdClass();
                    foreach ($value as $k => $v) {
                        $obj->{$k} = is_array($v) ? $this->convertNested($v) : $v;
                    }
                    $value = $obj;
                } else {
                    $value = array_map(fn ($v) => is_array($v) ? $this->convertNested($v) : $v, $value);
                }
            }
        }

        return $data;
    }

    private function isAssoc(array $arr): bool
    {
        if ([] === $arr) {
            return false;
        }

        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
