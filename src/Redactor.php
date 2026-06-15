<?php

declare(strict_types=1);

namespace Sirix\Redaction;

use InvalidArgumentException;
use ReflectionClass;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Rule\Default\DefaultRules;
use Sirix\Redaction\Rule\Matcher\KeyRuleMatcherInterface;
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
use function is_int;
use function is_object;
use function is_scalar;
use function is_string;
use function sprintf;

final class Redactor implements RedactorInterface
{
    private const OVERFLOW_KEY = '__redaction_overflow__';

    /**
     * @var array<string, RedactionRuleInterface>
     */
    private array $customExactRules = [];

    /**
     * @var list<KeyRuleMatcherInterface>
     */
    private array $customRuleMatchers = [];

    /**
     * @var array<string, RedactionRuleInterface>
     */
    private array $defaultExactRules = [];

    /**
     * @var array<string, RedactionRuleInterface>
     */
    private array $exactRules = [];

    private RedactorOptions $redactorOptions;

    /**
     * String keys define exact-key rules; integer keys define ordered key matchers.
     *
     * @param array<array-key, mixed> $customRules
     */
    public function __construct(
        array $customRules = [],
        bool $useDefaultRules = true,
        ?RedactorOptions $redactorOptions = null
    ) {
        $this->redactorOptions = $redactorOptions ?? new RedactorOptions();
        $this->defaultExactRules = $useDefaultRules ? $this->loadDefaultRules() : [];
        foreach ($customRules as $key => $rule) {
            if (is_string($key) && $rule instanceof RedactionRuleInterface) {
                $this->customExactRules[$key] = $rule;

                continue;
            }

            if (is_int($key) && $rule instanceof KeyRuleMatcherInterface) {
                $this->customRuleMatchers[] = $rule;

                continue;
            }

            throw new InvalidArgumentException(
                'Custom rules must be exact RedactionRuleInterface entries or ordered KeyRuleMatcherInterface entries'
            );
        }

        $this->exactRules = $this->defaultExactRules;
        foreach ($this->customExactRules as $key => $rule) {
            $this->exactRules[$key] = $rule;
        }
    }

    public function redact(mixed $rawData): mixed
    {
        $redactionContext = RedactionContext::forOptions($this->redactorOptions);

        return $this->processValueCopy($rawData, $redactionContext);
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

    private function processValueCopy(mixed $value, RedactionContext $redactionContext): mixed
    {
        if (is_array($value)) {
            if (! $this->enterDepth($redactionContext)) {
                $this->onLimit('maxDepth', ['kind' => 'array'], $redactionContext);

                return $this->overflowValue();
            }

            try {
                return $this->processContainerCopy($value, $redactionContext);
            } finally {
                $this->leaveDepth($redactionContext);
            }
        }

        if (is_object($value) && $this->shouldProcessObject($value)) {
            return $this->processObjectCopy($value, $redactionContext);
        }

        return $value;
    }

    private function shouldProcessObject(object $object): bool
    {
        return ! $object instanceof UnitEnum;
    }

    /**
     * @param array<mixed> $array
     *
     * @return array<mixed>
     */
    private function processContainerCopy(array $array, RedactionContext $redactionContext): array
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

            $processedItem = $this->processChildCopy($key, $item, $redactionContext);

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

    private function processObjectCopy(object $object, RedactionContext $redactionContext): mixed
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
                    $redactionContext,
                    false
                );

                return $properties;
            }

            return $this->processFullObjectCopy($object, $redactionContext);
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

    private function processFullObjectCopy(object $object, RedactionContext $redactionContext): object
    {
        [$properties] = $this->processObjectPropertiesCopy($object, $redactionContext, true);

        $copy = new stdClass();
        foreach ($properties as $name => $value) {
            $copy->{$name} = $value;
        }

        return $copy;
    }

    /**
     * @return array{0: array<string, mixed>, 1: bool, 2: bool}
     */
    private function processObjectPropertiesCopy(
        object $object,
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

            $processed = $this->processObjectPropertyValue($name, $value, $redactionContext);
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

            $processed = $this->processObjectPropertyValue($name, $value, $redactionContext);
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

    private function processObjectPropertyValue(string $key, mixed $value, RedactionContext $redactionContext): mixed
    {
        if ($this->hitNodeLimit('object_property', $key, $redactionContext)) {
            return $this->overflowValue();
        }

        return $this->maskValueCopy($key, $value, $redactionContext);
    }

    private function processChildCopy(int|string $key, mixed $item, RedactionContext $redactionContext): mixed
    {
        if ($this->hitNodeLimit('node', $key, $redactionContext)) {
            return $this->overflowValue();
        }

        if (is_scalar($item)) {
            if ([] === $this->customRuleMatchers) {
                if (isset($this->exactRules[$key])) {
                    return $this->applyRule($key, $item, $this->exactRules[$key], $redactionContext);
                }
            } else {
                $rule = $this->resolveRule($key);
                if ($rule instanceof RedactionRuleInterface) {
                    return $this->applyRule($key, $item, $rule, $redactionContext);
                }
            }
        }

        if (is_array($item) || (is_object($item) && $this->shouldProcessObject($item))) {
            return $this->processValueCopy($item, $redactionContext);
        }

        return $item;
    }

    private function maskValueCopy(string $key, mixed $value, RedactionContext $redactionContext): mixed
    {
        if (is_scalar($value)) {
            if ([] === $this->customRuleMatchers) {
                if (isset($this->exactRules[$key])) {
                    return $this->applyRule($key, $value, $this->exactRules[$key], $redactionContext);
                }
            } else {
                $rule = $this->resolveRule($key);
                if ($rule instanceof RedactionRuleInterface) {
                    return $this->applyRule($key, $value, $rule, $redactionContext);
                }
            }
        }

        if (is_array($value) || (is_object($value) && $this->shouldProcessObject($value))) {
            return $this->processValueCopy($value, $redactionContext);
        }

        return $value;
    }

    private function resolveRule(int|string $key): ?RedactionRuleInterface
    {
        if (! is_string($key)) {
            return null;
        }

        if ([] === $this->customRuleMatchers) {
            return $this->exactRules[$key] ?? null;
        }

        if (isset($this->customExactRules[$key])) {
            return $this->customExactRules[$key];
        }

        foreach ($this->customRuleMatchers as $customRuleMatcher) {
            if ($customRuleMatcher->matches($key)) {
                return $customRuleMatcher->rule();
            }
        }

        return $this->defaultExactRules[$key] ?? null;
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
