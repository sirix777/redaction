<?php

declare(strict_types=1);

namespace Test\Sirix\Redaction\Factory;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Factory\RedactorFactory;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorInterface;

final class RedactorFactoryTest extends TestCase
{
    private ContainerInterface|MockObject $container;

    public function setUp(): void
    {
        parent::setUp();

        $this->container = $this->createMock(ContainerInterface::class);
        $this->container->method('has')->willReturn(true);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testCreatesRedactorWithDefaultsWhenNoConfig(): void
    {
        $this->container->method('has')->with('config')->willReturn(false);
        $this->container->method('get')->with('config')->willReturn(null);

        $factory = new RedactorFactory();
        $service = $factory($this->container);

        $this->assertInstanceOf(Redactor::class, $service);
        $this->assertInstanceOf(RedactorInterface::class, $service);

        // Defaults from Redactor
        $this->assertSame('*', $service->getReplacement());
        $this->assertSame('%s', $service->getTemplate());
        $this->assertNull($service->getLengthLimit());
        $this->assertSame(ObjectViewModeEnum::Skip, $service->getObjectViewMode());
        $this->assertNull($service->getMaxDepth());
        $this->assertNull($service->getMaxItemsPerContainer());
        $this->assertNull($service->getMaxTotalNodes());
        $this->assertNull($service->getOnLimitExceededCallback());
        $this->assertNull($service->getOverflowPlaceholder());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testAppliesOptions(): void
    {
        $this->container->method('get')->with('config')->willReturn([
            'redactor' => [
                'options' => [
                    'replacement' => 'X',
                    'template' => '(%s)',
                    'length_limit' => '5',
                    'max_depth' => 2,
                    'max_items_per_container' => 1,
                    'max_total_nodes' => '10',
                    'object_view_mode' => ObjectViewModeEnum::Copy,
                    'on_limit_exceeded_callback' => static function(array $info): void {},
                    'overflow_placeholder' => '[TRUNCATED]',
                ],
            ],
        ]);

        $factory = new RedactorFactory();
        $service = $factory($this->container);

        $this->assertSame('X', $service->getReplacement());
        $this->assertSame('(%s)', $service->getTemplate());
        $this->assertSame(5, $service->getLengthLimit());
        $this->assertSame(2, $service->getMaxDepth());
        $this->assertSame(1, $service->getMaxItemsPerContainer());
        $this->assertSame(10, $service->getMaxTotalNodes());
        $this->assertSame(ObjectViewModeEnum::Copy, $service->getObjectViewMode());
        $this->assertIsCallable($service->getOnLimitExceededCallback());
        $this->assertSame('[TRUNCATED]', $service->getOverflowPlaceholder());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testAllowsNullForNumericOptions(): void
    {
        $this->container->method('get')->with('config')->willReturn([
            'redactor' => [
                'options' => [
                    'length_limit' => null,
                    'max_depth' => null,
                    'max_items_per_container' => null,
                    'max_total_nodes' => null,
                ],
            ],
        ]);

        $factory = new RedactorFactory();
        $service = $factory($this->container);

        $this->assertNull($service->getLengthLimit());
        $this->assertNull($service->getMaxDepth());
        $this->assertNull($service->getMaxItemsPerContainer());
        $this->assertNull($service->getMaxTotalNodes());
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testIgnoresInvalidTypes(): void
    {
        $this->container->method('get')->with('config')->willReturn([
            'redactor' => [
                'options' => [
                    'rules' => 'not-an-array',
                    'on_limit_exceeded_callback' => 'definitely-not-a-callable-name-123',
                ],
            ],
        ]);

        $factory = new RedactorFactory();
        $service = $factory($this->container);

        $this->assertNull($service->getOnLimitExceededCallback());
    }
}
