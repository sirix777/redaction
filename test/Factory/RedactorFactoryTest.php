<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Factory;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Factory\RedactorFactory;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorInterface;
use Sirix\Redaction\Rule\OffsetRule;

final class RedactorFactoryTest extends TestCase
{
    /**
     * @throws ContainerExceptionInterface
     */
    public function testCreatesRedactorWithDefaultsWhenNoConfig(): void
    {
        $redactorFactory = new RedactorFactory();
        $service = $redactorFactory($this->containerWithoutConfig());

        $this->assertInstanceOf(RedactorInterface::class, $service);
        $service = $this->assertRedactor($service);

        $this->assertSame('*', $service->getReplacement());
        $this->assertSame('%s', $service->getTemplate());
        $this->assertNull($service->getLengthLimit());
        $this->assertSame(ObjectViewModeEnum::Skip, $service->getObjectViewMode());
        $this->assertNull($service->getMaxDepth());
        $this->assertNull($service->getMaxItemsPerContainer());
        $this->assertNull($service->getMaxTotalNodes());
        $this->assertNull($service->getOnLimitExceededCallback());
        $this->assertSame('...', $service->getOverflowPlaceholder());
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function testAppliesStrictlyTypedOptions(): void
    {
        $callback = static function(array $info): void {};
        $redactorFactory = new RedactorFactory();
        $service = $redactorFactory($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'rules' => [
                        'password' => new OffsetRule(2),
                    ],
                    'use_default_rules' => false,
                    'replacement' => 'X',
                    'template' => '(%s)',
                    'length_limit' => 5,
                    'max_depth' => 2,
                    'max_items_per_container' => 1,
                    'max_total_nodes' => 10,
                    'object_view_mode' => ObjectViewModeEnum::Copy,
                    'on_limit_exceeded_callback' => $callback,
                    'overflow_placeholder' => '[TRUNCATED]',
                ],
            ],
        ]));

        $service = $this->assertRedactor($service);

        $this->assertSame('X', $service->getReplacement());
        $this->assertSame('(%s)', $service->getTemplate());
        $this->assertSame(5, $service->getLengthLimit());
        $this->assertSame(2, $service->getMaxDepth());
        $this->assertSame(1, $service->getMaxItemsPerContainer());
        $this->assertSame(10, $service->getMaxTotalNodes());
        $this->assertSame(ObjectViewModeEnum::Copy, $service->getObjectViewMode());
        $this->assertSame($callback, $service->getOnLimitExceededCallback());
        $this->assertSame('[TRUNCATED]', $service->getOverflowPlaceholder());
        $this->assertSame('se(XX', $service->redact(['password' => 'secret'])['password']);
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function testAcceptsObjectViewModeAsStringBackedValue(): void
    {
        $redactorFactory = new RedactorFactory();
        $service = $redactorFactory($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'object_view_mode' => 'copy',
                ],
            ],
        ]));

        $service = $this->assertRedactor($service);

        $this->assertSame(ObjectViewModeEnum::Copy, $service->getObjectViewMode());
    }

    /**
     * @throws ContainerExceptionInterface
     */
    public function testAllowsNullForNullableOptions(): void
    {
        $redactorFactory = new RedactorFactory();
        $service = $redactorFactory($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'length_limit' => null,
                    'max_depth' => null,
                    'max_items_per_container' => null,
                    'max_total_nodes' => null,
                    'overflow_placeholder' => null,
                ],
            ],
        ]));

        $service = $this->assertRedactor($service);

        $this->assertNull($service->getLengthLimit());
        $this->assertNull($service->getMaxDepth());
        $this->assertNull($service->getMaxItemsPerContainer());
        $this->assertNull($service->getMaxTotalNodes());
        $this->assertNull($service->getOverflowPlaceholder());
    }

    public function testRejectsNumericStringForLengthLimit(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'length_limit' => '5',
                ],
            ],
        ]));
    }

    public function testRejectsNumericStringForMaxDepth(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'max_depth' => '2',
                ],
            ],
        ]));
    }

    public function testRejectsNumericStringForMaxItemsPerContainer(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'max_items_per_container' => '2',
                ],
            ],
        ]));
    }

    public function testRejectsNumericStringForMaxTotalNodes(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'max_total_nodes' => '2',
                ],
            ],
        ]));
    }

    public function testRejectsNegativeMaxDepth(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'max_depth' => -1,
                ],
            ],
        ]));
    }

    public function testRejectsNegativeMaxItemsPerContainer(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'max_items_per_container' => -1,
                ],
            ],
        ]));
    }

    public function testRejectsNegativeMaxTotalNodes(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'max_total_nodes' => -1,
                ],
            ],
        ]));
    }

    public function testRejectsUnsafeTemplate(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'template' => '%100s',
                ],
            ],
        ]));
    }

    public function testRejectsInvalidRulesType(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'rules' => 'not-an-array',
                ],
            ],
        ]));
    }

    public function testRejectsInvalidRuleEntry(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'rules' => [
                        'password' => 'not-a-rule',
                    ],
                ],
            ],
        ]));
    }

    public function testRejectsNumericRuleKey(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'rules' => [
                        new OffsetRule(2),
                    ],
                ],
            ],
        ]));
    }

    public function testRejectsInvalidObjectViewMode(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'object_view_mode' => 'invalid',
                ],
            ],
        ]));
    }

    public function testRejectsInvalidCallback(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'on_limit_exceeded_callback' => 'definitely-not-a-callable-name-123',
                ],
            ],
        ]));
    }

    public function testRejectsInvalidOverflowPlaceholder(): void
    {
        $this->expectException(InvalidConfigValueException::class);

        (new RedactorFactory())($this->containerWithConfig([
            'redactor' => [
                'options' => [
                    'overflow_placeholder' => 123,
                ],
            ],
        ]));
    }

    public function testRejectsNonArrayConfigService(): void
    {
        $this->expectException(InvalidContainerServiceException::class);

        (new RedactorFactory())($this->containerWithConfig('not-an-array'));
    }

    private function assertRedactor(RedactorInterface $redactor): Redactor
    {
        $this->assertInstanceOf(Redactor::class, $redactor);

        return $redactor;
    }

    private function containerWithoutConfig(): ContainerInterface
    {
        return new class implements ContainerInterface {
            public function get(string $id): mixed
            {
                throw new class extends RuntimeException implements NotFoundExceptionInterface {};
            }

            public function has(string $id): bool
            {
                return false;
            }
        };
    }

    private function containerWithConfig(mixed $config): ContainerInterface
    {
        return new class($config) implements ContainerInterface {
            public function __construct(private readonly mixed $config) {}

            public function get(string $id): mixed
            {
                if ('config' !== $id) {
                    throw new class extends RuntimeException implements NotFoundExceptionInterface {};
                }

                return $this->config;
            }

            public function has(string $id): bool
            {
                return 'config' === $id;
            }
        };
    }
}
