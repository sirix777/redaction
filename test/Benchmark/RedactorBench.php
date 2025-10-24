<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Benchmark;

use PhpBench\Attributes as Bench;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\OffsetRule;

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
    private array $largeArray = [];

    private Redactor $deepRedactor;
    private array $deepData = [];

    private Redactor $objectCopyRedactor;
    private Redactor $objectRefRedactor;

    /** @var list<object> */
    private array $objectData = [];

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
    public function benchLargeObjectGraphReferenceMode(): void
    {
        $this->objectRefRedactor->redact($this->objectData);
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
            ->setMaxDepth(20)
            ->setOverflowPlaceholder('...')
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
            ->setObjectViewMode(ObjectViewModeEnum::Copy)
        ;

        $this->objectRefRedactor = (new Redactor($rules, false))
            ->setObjectViewMode(ObjectViewModeEnum::PublicArray)
        ;
    }
}

class User
{
    public function __construct(public string $username, public string $password, public string $token) {}
}
