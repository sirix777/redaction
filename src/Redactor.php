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
    private RedactorOptions $redactorOptions;

    /**
     * @param array<string, mixed> $customRules
     */
    public function __construct(
        array $customRules = [],
        bool $useDefaultRules = true,
        ?RedactorOptions $redactorOptions = null
    ) {
        $this->redactorOptions = $redactorOptions ?? new RedactorOptions();
        $this->rules = $useDefaultRules ? $this->loadDefaultRules() : [];
        foreach ($customRules as $key => $rule) {
            if (! $rule instanceof RedactionRuleInterface) {
                throw new InvalidArgumentException(
                    'All sensitive keys must be instances of RedactionRuleInterface'
                );
            }
            $this->rules[$key] = $rule;
        }
    }

    public function redact(mixed $rawData): mixed
    {
        $redactionContext = RedactionContext::forOptions($this->redactorOptions);

        return $this->processValueCopy($rawData, $this->rules, $redactionContext);
    }

    public function withReplacement(string $replacement): self
    {
        return $this->withOptions($this->redactorOptions->withReplacement($replacement));
    }

    public function getReplacement(): string
    {
        return $this->redactorOptions->replacement;
    }

    public function withTemplate(string $template): self
    {
        return $this->withOptions($this->redactorOptions->withTemplate($template));
    }

    public function getTemplate(): string
    {
        return $this->redactorOptions->template;
    }

    public function withLengthLimit(?int $lengthLimit): self
    {
        return $this->withOptions($this->redactorOptions->withLengthLimit($lengthLimit));
    }

    public function getLengthLimit(): ?int
    {
        return $this->redactorOptions->lengthLimit;
    }

    public function withObjectViewMode(ObjectViewModeEnum $objectViewModeEnum): self
    {
        return $this->withOptions($this->redactorOptions->withObjectViewMode($objectViewModeEnum));
    }

    public function getObjectViewMode(): ObjectViewModeEnum
    {
        return $this->redactorOptions->objectViewMode;
    }

    public function withMaxDepth(?int $depth): self
    {
        return $this->withOptions($this->redactorOptions->withMaxDepth($depth));
    }

    public function getMaxDepth(): ?int
    {
        return $this->redactorOptions->maxDepth;
    }

    public function withMaxItemsPerContainer(?int $count): self
    {
        return $this->withOptions($this->redactorOptions->withMaxItemsPerContainer($count));
    }

    public function getMaxItemsPerContainer(): ?int
    {
        return $this->redactorOptions->maxItemsPerContainer;
    }

    public function withMaxTotalNodes(?int $count): self
    {
        return $this->withOptions($this->redactorOptions->withMaxTotalNodes($count));
    }

    public function getMaxTotalNodes(): ?int
    {
        return $this->redactorOptions->maxTotalNodes;
    }

    public function withOnLimitExceededCallback(?callable $callback): self
    {
        return $this->withOptions($this->redactorOptions->withOnLimitExceededCallback($callback));
    }

    public function getOnLimitExceededCallback(): ?callable
    {
        return $this->redactorOptions->onLimitExceededCallback();
    }

    public function withOverflowPlaceholder(?string $value): self
    {
        return $this->withOptions($this->redactorOptions->withOverflowPlaceholder($value));
    }

    public function getOverflowPlaceholder(): ?string
    {
        return $this->redactorOptions->overflowPlaceholder;
    }

    private function withOptions(RedactorOptions $redactorOptions): self
    {
        $clone = clone $this;
        $clone->redactorOptions = $redactorOptions;

        return $clone;
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     */
    private function processValueCopy(mixed $value, array $rules, RedactionContext $redactionContext): mixed
    {
        if (is_array($value)) {
            if (! $this->enterDepth($redactionContext)) {
                $this->onLimit('maxDepth', ['kind' => 'array'], $redactionContext);

                return $this->overflowValue();
            }

            try {
                return $this->processContainerCopy($value, $rules, $redactionContext);
            } finally {
                $this->leaveDepth($redactionContext);
            }
        }

        if (is_object($value) && $this->shouldProcessObject($value)) {
            return $this->processObjectCopy($value, $rules, $redactionContext);
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
    private function processContainerCopy(array $array, array $rules, RedactionContext $redactionContext): array
    {
        $result = null;
        $count = 0;

        foreach ($array as $key => $item) {
            if (
                $redactionContext->totalNodeLimitExceeded
                || $this->hitContainerItemLimit($count, $key, $redactionContext)
            ) {
                $truncated = $result ?? array_slice($array, 0, $count, true);

                return $this->appendOverflowPlaceholder($truncated, $array);
            }

            $processedItem = $this->processChildCopy($key, $item, $rules, $redactionContext);

            if ($processedItem !== $item && null === $result) {
                $result = array_slice($array, 0, $count, true);
            }

            if (null !== $result) {
                $result[$key] = $processedItem;
            }

            ++$count;

            if ($this->hasExceededTotalNodeLimit($redactionContext) && $count < count($array)) {
                return $this->appendOverflowPlaceholder(
                    $result ?? array_slice($array, 0, $count, true),
                    $array
                );
            }
        }

        return $result ?? $array;
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     */
    private function processObjectCopy(object $object, array $rules, RedactionContext $redactionContext): mixed
    {
        if (ObjectViewModeEnum::Skip === $this->redactorOptions->objectViewMode) {
            return sprintf('[object %s]', get_debug_type($object));
        }

        if ($this->shouldSkipObject($object, $redactionContext)) {
            return $this->overflowValue();
        }

        $redactionContext->seenObjects?->offsetSet($object, true);
        ++$redactionContext->currentDepth;

        try {
            if (ObjectViewModeEnum::PublicArray === $this->redactorOptions->objectViewMode) {
                [$properties] = $this->processObjectPropertiesCopy(
                    $object,
                    $rules,
                    $redactionContext,
                    false
                );

                return $properties;
            }

            return $this->processFullObjectCopy($object, $rules, $redactionContext);
        } finally {
            --$redactionContext->currentDepth;
            $redactionContext->seenObjects?->offsetUnset($object);
        }
    }

    private function shouldSkipObject(object $object, RedactionContext $redactionContext): bool
    {
        if ($redactionContext->seenObjects?->offsetExists($object)) {
            $this->onLimit('cycle', ['class' => get_debug_type($object)], $redactionContext);

            return true;
        }

        if ($this->shouldStopForDepth($redactionContext)) {
            $this->onLimit(
                'maxDepth',
                [
                    'kind' => 'object',
                    'class' => get_debug_type($object),
                ],
                $redactionContext
            );

            return true;
        }

        return false;
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     */
    private function processFullObjectCopy(object $object, array $rules, RedactionContext $redactionContext): object
    {
        [$properties] = $this->processObjectPropertiesCopy($object, $rules, $redactionContext, true);

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
        RedactionContext $redactionContext,
        bool $includeNonPublic,
    ): array {
        $result = [];
        $changed = false;
        $truncated = false;
        $count = 0;

        $publicProperties = get_object_vars($object);
        foreach ($publicProperties as $name => $value) {
            if (
                $redactionContext->totalNodeLimitExceeded
                || $this->hitContainerItemLimit($count, $name, $redactionContext)
            ) {
                $this->appendObjectOverflowPlaceholder($result);
                $truncated = true;

                break;
            }

            $processed = $this->processObjectPropertyValue($name, $value, $rules, $redactionContext);
            $changed = $changed || $processed !== $value;
            $result[$name] = $processed;
            ++$count;

            if ($this->hasExceededTotalNodeLimit($redactionContext) && $count < count($publicProperties)) {
                $this->appendObjectOverflowPlaceholder($result);
                $truncated = true;

                break;
            }
        }

        if (! $includeNonPublic || $truncated) {
            return [$result, $changed, $truncated];
        }

        $reflectionClass = new ReflectionClass($object);
        foreach ($reflectionClass->getProperties() as $reflectionProperty) {
            if ($reflectionProperty->isPublic()) {
                continue;
            }

            if ($reflectionProperty->isStatic()) {
                continue;
            }

            if (! $reflectionProperty->isInitialized($object)) {
                continue;
            }

            $name = $reflectionProperty->getName();
            $value = $reflectionProperty->getValue($object);

            if (
                $redactionContext->totalNodeLimitExceeded
                || $this->hitContainerItemLimit($count, $name, $redactionContext)
            ) {
                $this->appendObjectOverflowPlaceholder($result);
                $truncated = true;

                break;
            }

            $processed = $this->processObjectPropertyValue($name, $value, $rules, $redactionContext);
            $changed = $changed || $processed !== $value;
            $result[$name] = $processed;
            ++$count;

            if ($this->hasExceededTotalNodeLimit($redactionContext)) {
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
        RedactionContext $redactionContext,
    ): mixed {
        if ($this->hitNodeLimit('object_property', $key, $redactionContext)) {
            return $this->overflowValue();
        }

        return $this->maskValueCopy($key, $value, $rules, $redactionContext);
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     */
    private function processChildCopy(
        int|string $key,
        mixed $item,
        array $rules,
        RedactionContext $redactionContext
    ): mixed {
        if ($this->hitNodeLimit('node', $key, $redactionContext)) {
            return $this->overflowValue();
        }

        if (is_scalar($item) && isset($rules[$key])) {
            return $this->applyRule($key, $item, $rules[$key], $redactionContext);
        }

        if (is_array($item) || (is_object($item) && $this->shouldProcessObject($item))) {
            return $this->processValueCopy($item, $rules, $redactionContext);
        }

        return $item;
    }

    /**
     * @param array<string, RedactionRuleInterface> $rules
     */
    private function maskValueCopy(string $key, mixed $value, array $rules, RedactionContext $redactionContext): mixed
    {
        $rule = $rules[$key] ?? null;
        if (is_scalar($value) && $rule instanceof RedactionRuleInterface) {
            return $this->applyRule($key, $value, $rule, $redactionContext);
        }

        if (is_array($value) || (is_object($value) && $this->shouldProcessObject($value))) {
            return $this->processValueCopy($value, $rules, $redactionContext);
        }

        return $value;
    }

    private function applyRule(
        int|string $key,
        mixed $value,
        RedactionRuleInterface $redactionRule,
        RedactionContext $redactionContext,
    ): ?string {
        try {
            return $redactionRule->apply((string) $value, $redactionContext->ruleContext);
        } catch (Throwable $exception) {
            $this->onLimit('ruleException', ['key' => $key, 'exception' => $exception::class], $redactionContext);

            return $this->overflowValue();
        }
    }

    private function enterDepth(RedactionContext $redactionContext): bool
    {
        if (
            null !== $this->redactorOptions->maxDepth
            && $redactionContext->currentDepth >= $this->redactorOptions->maxDepth
        ) {
            return false;
        }

        ++$redactionContext->currentDepth;

        return true;
    }

    private function leaveDepth(RedactionContext $redactionContext): void
    {
        --$redactionContext->currentDepth;
    }

    private function shouldStopForDepth(RedactionContext $redactionContext): bool
    {
        return null !== $this->redactorOptions->maxDepth
            && $redactionContext->currentDepth >= $this->redactorOptions->maxDepth;
    }

    private function hitContainerItemLimit(int $count, int|string $key, RedactionContext $redactionContext): bool
    {
        if (
            null !== $this->redactorOptions->maxItemsPerContainer
            && $count >= $this->redactorOptions->maxItemsPerContainer
        ) {
            $this->onLimit('maxItemsPerContainer', ['key' => $key], $redactionContext);

            return true;
        }

        return false;
    }

    private function hitNodeLimit(string $kind, int|string $key, RedactionContext $redactionContext): bool
    {
        if ($this->hasExceededTotalNodeLimit($redactionContext)) {
            return true;
        }

        if (++$redactionContext->nodesVisited > ($this->redactorOptions->maxTotalNodes ?? PHP_INT_MAX)) {
            $redactionContext->totalNodeLimitExceeded = true;
            $this->onLimit('maxTotalNodes', ['kind' => $kind, 'key' => $key], $redactionContext);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, mixed> $info
     */
    private function onLimit(string $type, array $info, RedactionContext $redactionContext): void
    {
        $callback = $this->redactorOptions->onLimitExceededCallback();
        if (null !== $callback) {
            try {
                $callback([
                    ...$info,
                    'type' => $type,
                    'depth' => $redactionContext->currentDepth,
                    'nodesVisited' => $redactionContext->nodesVisited,
                ]);
            } catch (Throwable) {
                // Limit callbacks are best-effort diagnostics and must not make redaction fail open.
            }
        }
    }

    private function hasExceededTotalNodeLimit(RedactionContext $redactionContext): bool
    {
        return $redactionContext->totalNodeLimitExceeded;
    }

    private function overflowValue(): ?string
    {
        return $this->redactorOptions->overflowPlaceholder;
    }

    /**
     * @param array<mixed> $truncated
     * @param array<mixed> $source
     *
     * @return array<mixed>
     */
    private function appendOverflowPlaceholder(array $truncated, array $source): array
    {
        if (null === $this->redactorOptions->overflowPlaceholder) {
            return $truncated;
        }

        if (array_is_list($source)) {
            $truncated[] = $this->redactorOptions->overflowPlaceholder;

            return $truncated;
        }

        $truncated[self::OVERFLOW_KEY] = $this->redactorOptions->overflowPlaceholder;

        return $truncated;
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function appendObjectOverflowPlaceholder(array &$properties): void
    {
        if (null !== $this->redactorOptions->overflowPlaceholder) {
            $properties[self::OVERFLOW_KEY] = $this->redactorOptions->overflowPlaceholder;
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
