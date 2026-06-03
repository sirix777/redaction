<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Benchmark;

use PhpBench\Attributes as Bench;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\FullMaskRule;
use Sirix\Redaction\Rule\OffsetRule;
use Sirix\Redaction\Rule\StartEndRule;

use function str_repeat;

/**
 * Micro-benchmarks for Sirix\Redaction\Redactor using phpbench/phpbench.
 *
 * Run examples:
 *   - php vendor/bin/phpbench run test/Benchmark --report=aggregate
 *   - php vendor/bin/phpbench run test/Benchmark/RedactorBench.php --report=default
 *
 * Notes:
 *   - Disable Xdebug and other profilers before running.
 *   - Use `php -d opcache.enable_cli=1` to stabilize timings.
 */
#[Bench\Iterations(8)]
#[Bench\Revs(50)]
#[Bench\Warmup(3)]
final class RedactorBench
{
    private Redactor $defaultRedactor;

    /** @var list<array<string, mixed>> */
    private array $largeArray = [];

    private Redactor $deepRedactor;

    /** @var array<string, mixed> */
    private array $deepData = [];

    private Redactor $objectCopyRedactor;
    private Redactor $objectPublicArrayRedactor;

    /** @var list<object> */
    private array $objectData = [];

    private Redactor $largeArrayItemsLimitRedactor;
    private Redactor $largeArrayNodeLimitRedactor;

    /** @var list<array<string, mixed>> */
    private array $noRedactionPayload = [];
    private Redactor $noRedactionRedactor;

    private Redactor $smallPayloadRedactor;

    /** @var array<string, mixed> */
    private array $smallPayload = [];

    private Redactor $longStringRedactor;

    /** @var array<string, string> */
    private array $longStringPayload = [];

    #[Bench\BeforeMethods(['setUpLargeArray'])]
    public function benchLargeArrayDefaultRules(): void
    {
        $this->defaultRedactor->redact($this->largeArray);
    }

    #[Bench\BeforeMethods(['setUpLargeArray'])]
    public function benchLargeArrayBaseline(): void
    {
        foreach ($this->largeArray as $item) {
            $tmp = $item;
        }
    }

    #[Bench\BeforeMethods(['setUpDeepNesting'])]
    public function benchDeepNestingWithLimits(): void
    {
        $this->deepRedactor->redact($this->deepData);
    }

    #[Bench\BeforeMethods(['setUpLargeObjectGraph'])]
    public function benchLargeObjectGraphCopyMode(): void
    {
        $this->objectCopyRedactor->redact($this->objectData);
    }

    #[Bench\BeforeMethods(['setUpLargeObjectGraph'])]
    public function benchLargeObjectGraphPublicArrayMode(): void
    {
        $this->objectPublicArrayRedactor->redact($this->objectData);
    }

    #[Bench\BeforeMethods(['setUpLargeArray'])]
    public function benchLargeArrayWithMaxItemsLimit(): void
    {
        $this->largeArrayItemsLimitRedactor->redact($this->largeArray);
    }

    #[Bench\BeforeMethods(['setUpLargeArray'])]
    public function benchLargeArrayWithMaxTotalNodes(): void
    {
        $this->largeArrayNodeLimitRedactor->redact($this->largeArray);
    }

    #[Bench\BeforeMethods(['setUpNoRedactionPayload'])]
    public function benchNoRedactionCopyOnWrite(): void
    {
        $this->noRedactionRedactor->redact($this->noRedactionPayload);
    }

    #[Bench\BeforeMethods(['setUpSmallPayload'])]
    public function benchRepeatedSmallPayloadRedaction(): void
    {
        for ($i = 0; $i < 1000; ++$i) {
            $this->smallPayloadRedactor->redact($this->smallPayload);
        }
    }

    #[Bench\BeforeMethods(['setUpLongStringPayload'])]
    public function benchVeryLongStringWithLengthLimit(): void
    {
        $this->longStringRedactor->redact($this->longStringPayload);
    }

    public function setUpLargeArray(): void
    {
        $records = [];
        $count = 2000;
        for ($i = 0; $i < $count; ++$i) {
            $records[] = [
                'id' => $i,
                'name' => 'John Doe',
                'email' => 'user' . $i . '@example.com',
                'password' => 'p@ssw0rd' . $i,
                'address' => '221B Baker Street',
            ];
        }

        $this->largeArray = $records;
        $this->defaultRedactor = new Redactor();
        $this->largeArrayItemsLimitRedactor = (new Redactor())->withMaxItemsPerContainer(10);
        $this->largeArrayNodeLimitRedactor = (new Redactor())->withMaxTotalNodes(50);
    }

    public function setUpNoRedactionPayload(): void
    {
        $records = [];
        for ($i = 0; $i < 2000; ++$i) {
            $records[] = [
                'id' => $i,
                'status' => 'ok',
                'metadata' => [
                    'source' => 'benchmark',
                    'sequence' => $i,
                ],
            ];
        }

        $this->noRedactionPayload = $records;
        $this->noRedactionRedactor = new Redactor([], false);
    }

    public function setUpDeepNesting(): void
    {
        $depth = 40;
        $data = ['meta' => 'ok'];
        $cur = &$data;
        for ($i = 0; $i < $depth; ++$i) {
            $cur['level'] = $i;
            $cur['child'] = [];
            $cur = &$cur['child'];
        }
        unset($cur);

        $this->deepData = $data;
        $this->deepRedactor = (new Redactor())
            ->withMaxDepth(20)
            ->withOverflowPlaceholder('...')
        ;
    }

    public function setUpSmallPayload(): void
    {
        $this->smallPayload = [
            'email' => 'john.doe@example.com',
            'password' => 'secret-password',
            'nested' => [
                'token' => 'abcdef123456',
            ],
        ];

        $this->smallPayloadRedactor = new Redactor([
            'password' => new OffsetRule(2),
            'token' => new OffsetRule(4),
        ]);
    }

    public function setUpLongStringPayload(): void
    {
        $this->longStringPayload = [
            'blob' => str_repeat('a', 100_000),
            'full' => str_repeat('b', 100_000),
        ];

        $this->longStringRedactor = (new Redactor([
            'blob' => new StartEndRule(2, 2),
            'full' => new FullMaskRule(),
        ], false))
            ->withLengthLimit(64)
        ;
    }

    public function setUpLargeObjectGraph(): void
    {
        $n = 300;
        $objects = [];
        for ($i = 0; $i < $n; ++$i) {
            $objects[] = new User(
                'user' . $i,
                'secret' . $i,
                'tok' . $i
            );
        }
        $this->objectData = $objects;

        $rules = [
            'password' => new OffsetRule(2),
            'token' => new OffsetRule(3),
        ];

        $this->objectCopyRedactor = (new Redactor($rules, false))
            ->withObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $this->objectPublicArrayRedactor = (new Redactor($rules, false))
            ->withObjectViewMode(ObjectViewModeEnum::PublicArray)
        ;
    }
}

class User
{
    public function __construct(public string $username, public string $password, public string $token) {}
}
