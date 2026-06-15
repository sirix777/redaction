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
use Sirix\Redaction\Rule\Matcher\KeyRuleMatcherInterface;
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
        $containerResolver = ContainerResolver::forFactory($container, self::class);
        $configReader      = ConfigReader::fromContainer($containerResolver);

        $rules = $configReader->array('redactor.options.rules', []);
        $this->assertRules($rules);

        $callback    = $this->nullableCallable($configReader, 'redactor.options.on_limit_exceeded_callback');
        $placeholder = $this->nullableString($configReader, 'redactor.options.overflow_placeholder', '...');

        $redactorOptions = new RedactorOptions(
            replacement: $configReader->string('redactor.options.replacement', '*'),
            template: $this->template($configReader, 'redactor.options.template'),
            lengthLimit: $this->nullableInt($configReader, 'redactor.options.length_limit'),
            objectViewMode: $configReader->enum(
                'redactor.options.object_view_mode',
                ObjectViewModeEnum::class,
                ObjectViewModeEnum::Skip,
            ),
            maxDepth: $this->nullableInt($configReader, 'redactor.options.max_depth'),
            maxItemsPerContainer: $this->nullableInt($configReader, 'redactor.options.max_items_per_container'),
            maxTotalNodes: $this->nullableInt($configReader, 'redactor.options.max_total_nodes'),
            onLimitExceededCallback: $callback,
            overflowPlaceholder: $placeholder,
        );

        return new Redactor(
            customRules: $rules,
            useDefaultRules: $configReader->bool('redactor.options.use_default_rules', true),
            redactorOptions: $redactorOptions,
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
            if (is_string($key) && $rule instanceof RedactionRuleInterface) {
                continue;
            }

            if (is_int($key) && $rule instanceof KeyRuleMatcherInterface) {
                continue;
            }

            throw InvalidConfigValueException::forType(
                "redactor.options.rules.{$key}",
                'array<string, RedactionRuleInterface>|list<KeyRuleMatcherInterface>',
                $rule,
                self::class,
            );
        }
    }

    /**
     * @throws InvalidConfigValueException
     */
    private function nullableInt(ConfigReader $configReader, string $path): ?int
    {
        if (! $configReader->has($path)) {
            return null;
        }

        $value = $configReader->get($path);
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
    private function template(ConfigReader $configReader, string $path): string
    {
        $template = $configReader->string($path, '%s');
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
    private function nullableCallable(ConfigReader $configReader, string $path): ?callable
    {
        if (! $configReader->has($path)) {
            return null;
        }

        $value = $configReader->get($path);
        if (null === $value || is_callable($value)) {
            return $value;
        }

        throw InvalidConfigValueException::forType($path, 'callable|null', $value, self::class);
    }

    /**
     * @throws InvalidConfigValueException
     */
    private function nullableString(ConfigReader $configReader, string $path, ?string $default = null): ?string
    {
        if (! $configReader->has($path)) {
            return $default;
        }

        $value = $configReader->get($path);
        if (null === $value || is_string($value)) {
            return $value;
        }

        throw InvalidConfigValueException::forType($path, 'string|null', $value, self::class);
    }
}
