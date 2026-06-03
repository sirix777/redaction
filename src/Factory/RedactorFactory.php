<?php

declare(strict_types=1);

namespace Sirix\Redaction\Factory;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Sirix\ContainerResolver\ConfigReader;
use Sirix\ContainerResolver\ContainerResolver;
use Sirix\ContainerResolver\Exception\InvalidConfigValueException;
use Sirix\ContainerResolver\Exception\InvalidContainerServiceException;
use Sirix\ContainerResolver\Exception\MissingContainerServiceException;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorInterface;
use Sirix\Redaction\RedactorOptions;
use Sirix\Redaction\Rule\RedactionRuleInterface;

use function is_callable;
use function is_int;
use function is_string;
use function preg_match;
use function substr_count;

final class RedactorFactory
{
    /**
     * @throws MissingContainerServiceException
     * @throws InvalidContainerServiceException
     * @throws InvalidConfigValueException
     * @throws ContainerExceptionInterface
     */
    public function __invoke(ContainerInterface $container): RedactorInterface
    {
        $resolver = ContainerResolver::forFactory($container, self::class);
        $config = ConfigReader::fromContainer($resolver);

        $rules = $config->array('redactor.options.rules', []);
        $this->assertRules($rules);

        $callback = $this->nullableCallable($config, 'redactor.options.on_limit_exceeded_callback');
        $placeholder = $this->nullableString($config, 'redactor.options.overflow_placeholder', '...');

        $options = new RedactorOptions(
            replacement: $config->string('redactor.options.replacement', '*'),
            template: $this->template($config, 'redactor.options.template'),
            lengthLimit: $this->nullableInt($config, 'redactor.options.length_limit'),
            objectViewMode: $config->enum(
                'redactor.options.object_view_mode',
                ObjectViewModeEnum::class,
                ObjectViewModeEnum::Skip,
            ),
            maxDepth: $this->nullableInt($config, 'redactor.options.max_depth'),
            maxItemsPerContainer: $this->nullableInt($config, 'redactor.options.max_items_per_container'),
            maxTotalNodes: $this->nullableInt($config, 'redactor.options.max_total_nodes'),
            onLimitExceededCallback: $callback,
            overflowPlaceholder: $placeholder,
        );

        return new Redactor(
            customRules: $rules,
            useDefaultRules: $config->bool('redactor.options.use_default_rules', true),
            options: $options,
        );
    }

    /**
     * @param array<mixed> $rules
     *
     * @throws InvalidConfigValueException
     */
    private function assertRules(array $rules): void
    {
        foreach ($rules as $key => $rule) {
            if (! is_string($key)) {
                throw InvalidConfigValueException::forType(
                    'redactor.options.rules',
                    'array<string, RedactionRuleInterface>',
                    $rules,
                    self::class,
                );
            }

            if (! $rule instanceof RedactionRuleInterface) {
                throw InvalidConfigValueException::forType(
                    "redactor.options.rules.{$key}",
                    RedactionRuleInterface::class,
                    $rule,
                    self::class,
                );
            }
        }
    }

    /**
     * @throws InvalidConfigValueException
     */
    private function nullableInt(ConfigReader $config, string $path): ?int
    {
        if (! $config->has($path)) {
            return null;
        }

        $value = $config->get($path);
        if (null === $value) {
            return null;
        }

        if (! is_int($value)) {
            throw InvalidConfigValueException::forType($path, 'int|null', $value, self::class);
        }

        if ($value < 0) {
            throw InvalidConfigValueException::forType($path, 'int >= 0|null', $value, self::class);
        }

        return $value;
    }

    /**
     * @throws InvalidConfigValueException
     */
    private function template(ConfigReader $config, string $path): string
    {
        $template = $config->string($path, '%s');
        if (1 !== substr_count($template, '%s') || preg_match('/%(?!s)/', $template)) {
            throw InvalidConfigValueException::forType(
                $path,
                'safe mask template with exactly one plain %s',
                $template,
                self::class
            );
        }

        return $template;
    }

    /**
     * @throws InvalidConfigValueException
     */
    private function nullableCallable(ConfigReader $config, string $path): ?callable
    {
        if (! $config->has($path)) {
            return null;
        }

        $value = $config->get($path);
        if (null === $value || is_callable($value)) {
            return $value;
        }

        throw InvalidConfigValueException::forType($path, 'callable|null', $value, self::class);
    }

    /**
     * @throws InvalidConfigValueException
     */
    private function nullableString(ConfigReader $config, string $path, ?string $default = null): ?string
    {
        if (! $config->has($path)) {
            return $default;
        }

        $value = $config->get($path);
        if (null === $value || is_string($value)) {
            return $value;
        }

        throw InvalidConfigValueException::forType($path, 'string|null', $value, self::class);
    }
}
