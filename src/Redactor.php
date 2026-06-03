<?php

declare(strict_types=1);

namespace Sirix\Redaction;

use InvalidArgumentException;
use ReflectionClass;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Rule\Default\DefaultRules;
use Sirix\Redaction\Rule\RedactionRuleInterface;
use stdClass;
use Throwable;
use UnitEnum;

use function array_is_list;
use function array_slice;
use function count;
use function get_debug_type;
use function get_object_vars;
use function is_array;
use function is_object;
use function is_scalar;
use function sprintf;

final class Redactor implements RedactorInterface
{
    private const OVERFLOW_KEY = '__redaction_overflow__';

    /**
     * @var array<string, RedactionRuleInterface>
     */
    private array $rules = [];
    private RedactorOptions $options;

    /**
     * @param array<string, mixed> $customRules
     */
    public function __construct(array $customRules = [], bool $useDefaultRules = true, ?RedactorOptions $options = null)
    {
        $this->options = $options ?? new RedactorOptions();
        $this->rules = $useDefaultRules ? $this->loadDefaultRules() : [];
        foreach ($customRules as $key => $rule) {
            if (! $rule instanceof RedactionRuleInterface) {
                throw new InvalidArgumentException('All sensitive keys must be instances of RedactionRuleInterface');
            }
            $this->rules[$key] = $rule;
        }
    }

    public function redact(mixed $rawData): mixed
    {
        $context = RedactionContext::forOptions($this->options);

        return $this->processValueCopy($rawData, $this->rules, $context);
    }

    public function withReplacement(string $replacement): self
    {
        return $this->withOptions($this->options->withReplacement($replacement));
    }

    public function getReplacement(): string
    {
        return $this->options->replacement;
    }

    public function withTemplate(string $template): self
    {
        return $this->withOptions($this->options->withTemplate($template));
    }

    public function getTemplate(): string
    {
        return $this->options->template;
    }

    public function withLengthLimit(?int $lengthLimit): self
    {
        return $this->withOptions($this->options->withLengthLimit($lengthLimit));
    }

    public function getLengthLimit(): ?int
    {
        return $this->options->lengthLimit;
    }

    public function withObjectViewMode(ObjectViewModeEnum $mode): self
    {
        return $this->withOptions($this->options->withObjectViewMode($mode));
    }

    public function getObjectViewMode(): ObjectViewModeEnum
    {
        return $this->options->objectViewMode;
    }

    public function withMaxDepth(?int $depth): self
    {
        return $this->withOptions($this->options->withMaxDepth($depth));
    }

    public function getMaxDepth(): ?int
    {
        return $this->options->maxDepth;
    }

    public function withMaxItemsPerContainer(?int $count): self
    {
        return $this->withOptions($this->options->withMaxItemsPerContainer($count));
    }

    public function getMaxItemsPerContainer(): ?int
    {
        return $this->options->maxItemsPerContainer;
    }

    public function withMaxTotalNodes(?int $count): self
    {
        return $this->withOptions($this->options->withMaxTotalNodes($count));
    }

    public function getMaxTotalNodes(): ?int
    {
        return $this->options->maxTotalNodes;
    }

    public function withOnLimitExceededCallback(?callable $callback): self
    {
        return $this->withOptions($this->options->withOnLimitExceededCallback($callback));
    }

    public function getOnLimitExceededCallback(): ?callable
    {
        return $this->options->onLimitExceededCallback();
    }

    public function withOverflowPlaceholder(?string $value): self
    {
        return $this->withOptions($this->options->withOverflowPlaceholder($value));
    }

    public function getOverflowPlaceholder(): ?string
    {
        return $this->options->overflowPlaceholder;
    }

    private function withOptions(RedactorOptions $options): self
    {
        $clone = clone $this;
        $clone->options = $options;

        return $clone;
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     */
    private function processValueCopy(mixed $value, array $rules, RedactionContext $context): mixed
    {
        if (is_array($value)) {
            if (! $this->enterDepth($context)) {
                $this->onLimit('maxDepth', ['kind' => 'array'], $context);

                return $this->overflowValue();
            }

            try {
                return $this->processContainerCopy($value, $rules, $context);
            } finally {
                $this->leaveDepth($context);
            }
        }

        if (is_object($value) && $this->shouldProcessObject($value)) {
            return $this->processObjectCopy($value, $rules, $context);
        }

        return $value;
    }

    private function shouldProcessObject(object $object): bool
    {
        return ! $object instanceof UnitEnum;
    }

    /**
     * @param array<mixed>                          $array
     * @param array<string, RedactionRuleInterface> $rules
     *
     * @return array<mixed>
     */
    private function processContainerCopy(array $array, array $rules, RedactionContext $context): array
    {
        $result = null;
        $count = 0;

        foreach ($array as $key => $item) {
            if ($context->totalNodeLimitExceeded || $this->hitContainerItemLimit($count, $key, $context)) {
                $truncated = $result ?? array_slice($array, 0, $count, true);

                return $this->appendOverflowPlaceholder($truncated, $array);
            }

            $processedItem = $this->processChildCopy($key, $item, $rules, $context);

            if ($processedItem !== $item && null === $result) {
                $result = array_slice($array, 0, $count, true);
            }

            if (null !== $result) {
                $result[$key] = $processedItem;
            }

            ++$count;

            if ($this->hasExceededTotalNodeLimit($context) && $count < count($array)) {
                return $this->appendOverflowPlaceholder($result ?? array_slice($array, 0, $count, true), $array);
            }
        }

        return $result ?? $array;
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     */
    private function processObjectCopy(object $object, array $rules, RedactionContext $context): mixed
    {
        if (ObjectViewModeEnum::Skip === $this->options->objectViewMode) {
            return sprintf('[object %s]', get_debug_type($object));
        }

        if ($this->shouldSkipObject($object, $context)) {
            return $this->overflowValue();
        }

        $context->seenObjects?->offsetSet($object, true);
        ++$context->currentDepth;

        try {
            if (ObjectViewModeEnum::PublicArray === $this->options->objectViewMode) {
                [$properties] = $this->processObjectPropertiesCopy($object, $rules, $context, false);

                return $properties;
            }

            return $this->processFullObjectCopy($object, $rules, $context);
        } finally {
            --$context->currentDepth;
            $context->seenObjects?->offsetUnset($object);
        }
    }

    private function shouldSkipObject(object $object, RedactionContext $context): bool
    {
        if ($context->seenObjects?->offsetExists($object)) {
            $this->onLimit('cycle', ['class' => get_debug_type($object)], $context);

            return true;
        }

        if ($this->shouldStopForDepth($context)) {
            $this->onLimit('maxDepth', ['kind' => 'object', 'class' => get_debug_type($object)], $context);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     */
    private function processFullObjectCopy(object $object, array $rules, RedactionContext $context): object
    {
        [$properties] = $this->processObjectPropertiesCopy($object, $rules, $context, true);

        $copy = new stdClass();
        foreach ($properties as $name => $value) {
            $copy->{$name} = $value;
        }

        return $copy;
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     *
     * @return array{0: array<string, mixed>, 1: bool, 2: bool}
     */
    private function processObjectPropertiesCopy(
        object $object,
        array $rules,
        RedactionContext $context,
        bool $includeNonPublic,
    ): array {
        $result = [];
        $changed = false;
        $truncated = false;
        $count = 0;

        $publicProperties = get_object_vars($object);
        foreach ($publicProperties as $name => $value) {
            if ($context->totalNodeLimitExceeded || $this->hitContainerItemLimit($count, $name, $context)) {
                $this->appendObjectOverflowPlaceholder($result);
                $truncated = true;

                break;
            }

            $processed = $this->processObjectPropertyValue($name, $value, $rules, $context);
            $changed = $changed || $processed !== $value;
            $result[$name] = $processed;
            ++$count;

            if ($this->hasExceededTotalNodeLimit($context) && $count < count($publicProperties)) {
                $this->appendObjectOverflowPlaceholder($result);
                $truncated = true;

                break;
            }
        }

        if (! $includeNonPublic || $truncated) {
            return [$result, $changed, $truncated];
        }

        $ref = new ReflectionClass($object);
        foreach ($ref->getProperties() as $prop) {
            if ($prop->isPublic()) {
                continue;
            }

            if ($prop->isStatic()) {
                continue;
            }

            if (! $prop->isInitialized($object)) {
                continue;
            }

            $name = $prop->getName();
            $value = $prop->getValue($object);

            if ($context->totalNodeLimitExceeded || $this->hitContainerItemLimit($count, $name, $context)) {
                $this->appendObjectOverflowPlaceholder($result);
                $truncated = true;

                break;
            }

            $processed = $this->processObjectPropertyValue($name, $value, $rules, $context);
            $changed = $changed || $processed !== $value;
            $result[$name] = $processed;
            ++$count;

            if ($this->hasExceededTotalNodeLimit($context)) {
                $this->appendObjectOverflowPlaceholder($result);
                $truncated = true;

                break;
            }
        }

        return [$result, $changed, $truncated];
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     */
    private function processObjectPropertyValue(
        string $key,
        mixed $value,
        array $rules,
        RedactionContext $context,
    ): mixed {
        if ($this->hitNodeLimit('object_property', $key, $context)) {
            return $this->overflowValue();
        }

        return $this->maskValueCopy($key, $value, $rules, $context);
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     */
    private function processChildCopy(int|string $key, mixed $item, array $rules, RedactionContext $context): mixed
    {
        if ($this->hitNodeLimit('node', $key, $context)) {
            return $this->overflowValue();
        }

        if (is_scalar($item) && isset($rules[$key])) {
            return $this->applyRule($key, $item, $rules[$key], $context);
        }

        if (is_array($item) || (is_object($item) && $this->shouldProcessObject($item))) {
            return $this->processValueCopy($item, $rules, $context);
        }

        return $item;
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     */
    private function maskValueCopy(string $key, mixed $value, array $rules, RedactionContext $context): mixed
    {
        $rule = $rules[$key] ?? null;
        if (is_scalar($value) && $rule instanceof RedactionRuleInterface) {
            return $this->applyRule($key, $value, $rule, $context);
        }

        if (is_array($value) || (is_object($value) && $this->shouldProcessObject($value))) {
            return $this->processValueCopy($value, $rules, $context);
        }

        return $value;
    }

    private function applyRule(
        int|string $key,
        mixed $value,
        RedactionRuleInterface $rule,
        RedactionContext $context,
    ): ?string {
        try {
            return $rule->apply((string) $value, $context->ruleContext);
        } catch (Throwable $exception) {
            $this->onLimit('ruleException', ['key' => $key, 'exception' => $exception::class], $context);

            return $this->overflowValue();
        }
    }

    private function enterDepth(RedactionContext $context): bool
    {
        if (null !== $this->options->maxDepth && $context->currentDepth >= $this->options->maxDepth) {
            return false;
        }

        ++$context->currentDepth;

        return true;
    }

    private function leaveDepth(RedactionContext $context): void
    {
        --$context->currentDepth;
    }

    private function shouldStopForDepth(RedactionContext $context): bool
    {
        return null !== $this->options->maxDepth && $context->currentDepth >= $this->options->maxDepth;
    }

    private function hitContainerItemLimit(int $count, int|string $key, RedactionContext $context): bool
    {
        if (null !== $this->options->maxItemsPerContainer && $count >= $this->options->maxItemsPerContainer) {
            $this->onLimit('maxItemsPerContainer', ['key' => $key], $context);

            return true;
        }

        return false;
    }

    private function hitNodeLimit(string $kind, int|string $key, RedactionContext $context): bool
    {
        if ($this->hasExceededTotalNodeLimit($context)) {
            return true;
        }

        if (++$context->nodesVisited > ($this->options->maxTotalNodes ?? PHP_INT_MAX)) {
            $context->totalNodeLimitExceeded = true;
            $this->onLimit('maxTotalNodes', ['kind' => $kind, 'key' => $key], $context);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $info
     */
    private function onLimit(string $type, array $info, RedactionContext $context): void
    {
        $callback = $this->options->onLimitExceededCallback();
        if (null !== $callback) {
            try {
                $callback([
                    ...$info,
                    'type' => $type,
                    'depth' => $context->currentDepth,
                    'nodesVisited' => $context->nodesVisited,
                ]);
            } catch (Throwable) {
                // Limit callbacks are best-effort diagnostics and must not make redaction fail open.
            }
        }
    }

    private function hasExceededTotalNodeLimit(RedactionContext $context): bool
    {
        return $context->totalNodeLimitExceeded;
    }

    private function overflowValue(): ?string
    {
        return $this->options->overflowPlaceholder;
    }

    /**
     * @param array<mixed> $truncated
     * @param array<mixed> $source
     *
     * @return array<mixed>
     */
    private function appendOverflowPlaceholder(array $truncated, array $source): array
    {
        if (null === $this->options->overflowPlaceholder) {
            return $truncated;
        }

        if (array_is_list($source)) {
            $truncated[] = $this->options->overflowPlaceholder;

            return $truncated;
        }

        $truncated[self::OVERFLOW_KEY] = $this->options->overflowPlaceholder;

        return $truncated;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function appendObjectOverflowPlaceholder(array &$properties): void
    {
        if (null !== $this->options->overflowPlaceholder) {
            $properties[self::OVERFLOW_KEY] = $this->options->overflowPlaceholder;
        }
    }

    /**
     * @return array<string, RedactionRuleInterface>
     */
    private function loadDefaultRules(): array
    {
        return DefaultRules::getAll();
    }
}
