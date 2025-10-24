<?php

declare(strict_types=1);

namespace Sirix\Redaction;

use InvalidArgumentException;
use ReflectionClass;
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Rule\Default\DefaultRules;
use Sirix\Redaction\Rule\RedactionRuleInterface;
use SplObjectStorage;
use stdClass;
use UnitEnum;

use function array_merge;
use function get_debug_type;
use function get_object_vars;
use function is_array;
use function is_callable;
use function is_object;
use function is_scalar;
use function iterator_to_array;
use function sprintf;

final class Redactor implements RedactorInterface
{
    /**
     * @var array<string, array<string, mixed>|RedactionRuleInterface>
     */
    private array $rules = [];
    private string $replacement = '*';
    private string $template = '%s';
    private ?int $lengthLimit = null;
    private ObjectViewModeEnum $objectViewMode = ObjectViewModeEnum::Skip;
    private ?int $maxDepth = null;
    private ?int $maxItemsPerContainer = null;
    private ?int $maxTotalNodes = null;

    /**
     * @var null|callable
     */
    private $onLimitExceededCallback;
    private ?string $overflowPlaceholder = null;
    private int $currentDepth = 0;
    private int $nodesVisited = 0;

    /**
     * @var null|SplObjectStorage<object, bool>
     */
    private ?SplObjectStorage $seenObjects = null;

    /** @var array<string, ReflectionClass<object>> */
    private static array $reflectionCache = [];

    /**
     * @param array<string, mixed> $customRules
     */
    public function __construct(array $customRules = [], bool $useDefaultRules = true)
    {
        $this->rules = $useDefaultRules ? $this->loadDefaultRules() : [];
        foreach ($customRules as $key => $rule) {
            if (! $rule instanceof RedactionRuleInterface) {
                throw new InvalidArgumentException('All sensitive keys must be instances of RedactionRuleInterface');
            }
            // Custom rules override defaults if keys overlap
            $this->rules[$key] = $rule;
        }
    }

    public function redact(mixed $rawData): mixed
    {
        $this->resetState();

        $result = $this->processValueCopy($rawData, $this->rules);

        $this->seenObjects = null;

        return $result;
    }

    public function setReplacement(string $replacement): RedactorInterface
    {
        $this->replacement = $replacement;

        return $this;
    }

    public function getReplacement(): string
    {
        return $this->replacement;
    }

    public function setTemplate(string $template): RedactorInterface
    {
        $this->template = $template;

        return $this;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function setLengthLimit(?int $lengthLimit): RedactorInterface
    {
        $this->lengthLimit = $lengthLimit;

        return $this;
    }

    public function getLengthLimit(): ?int
    {
        return $this->lengthLimit;
    }

    public function setObjectViewMode(ObjectViewModeEnum $mode): RedactorInterface
    {
        $this->objectViewMode = $mode;

        return $this;
    }

    public function getObjectViewMode(): ObjectViewModeEnum
    {
        return $this->objectViewMode;
    }

    public function setMaxDepth(?int $depth): RedactorInterface
    {
        if (null !== $depth && $depth < 0) {
            throw new InvalidArgumentException('maxDepth must be null or >= 0');
        }
        $this->maxDepth = $depth;

        return $this;
    }

    public function getMaxDepth(): ?int
    {
        return $this->maxDepth;
    }

    public function setMaxItemsPerContainer(?int $count): RedactorInterface
    {
        if (null !== $count && $count < 0) {
            throw new InvalidArgumentException('maxItemsPerContainer must be null or >= 0');
        }
        $this->maxItemsPerContainer = $count;

        return $this;
    }

    public function getMaxItemsPerContainer(): ?int
    {
        return $this->maxItemsPerContainer;
    }

    public function setMaxTotalNodes(?int $count): RedactorInterface
    {
        if (null !== $count && $count < 0) {
            throw new InvalidArgumentException('maxTotalNodes must be null or >= 0');
        }
        $this->maxTotalNodes = $count;

        return $this;
    }

    public function getMaxTotalNodes(): ?int
    {
        return $this->maxTotalNodes;
    }

    public function setOnLimitExceededCallback(?callable $callback): RedactorInterface
    {
        $this->onLimitExceededCallback = $callback;

        return $this;
    }

    public function getOnLimitExceededCallback(): ?callable
    {
        return $this->onLimitExceededCallback;
    }

    public function setOverflowPlaceholder(?string $value): RedactorInterface
    {
        $this->overflowPlaceholder = $value;

        return $this;
    }

    public function getOverflowPlaceholder(): ?string
    {
        return $this->overflowPlaceholder;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processValue(mixed &$value, array $rules): void
    {
        if (is_array($value)) {
            $this->processContainer($value, $rules);
        } elseif (is_object($value) && $this->shouldProcessObject($value)) {
            $this->processObject($value, $rules);
        }
    }

    private function shouldProcessObject(object $object): bool
    {
        return ! $object instanceof UnitEnum;
    }

    /**
     * @param array<string, mixed>                                       $array
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processContainer(array &$array, array $rules): void
    {
        if (! $this->enterDepth()) {
            $this->onLimit('maxDepth', ['kind' => 'array']);
            $this->replaceIfNeeded($array);

            return;
        }

        $count = 0;

        foreach ($array as $key => &$item) {
            if ($this->checkLimitPerContainer($count, $key)) {
                continue;
            }

            $this->processChild($key, $item, $rules);
        }

        unset($item);
        $this->leaveDepth();
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processObject(mixed &$value, array $rules): void
    {
        if (ObjectViewModeEnum::Skip === $this->objectViewMode) {
            $value = sprintf('[object %s]', get_debug_type($value));

            return;
        }

        $object = $value;

        if ($this->shouldSkipObject($object)) {
            $this->replaceIfNeeded($value);

            return;
        }

        $this->seenObjects?->attach($object, true);

        ++$this->currentDepth;

        $value = match ($this->objectViewMode) {
            ObjectViewModeEnum::PublicArray => $this->processObjectAsArray($object, $rules),
            default => $this->processObjectWithReflection($object, $rules),
        };

        --$this->currentDepth;
    }

    private function shouldSkipObject(object $object): bool
    {
        if ($this->seenObjects?->contains($object)) {
            $this->onLimit('cycle', ['class' => get_debug_type($object)]);

            return true;
        }

        if ($this->shouldStopForDepth()) {
            $this->onLimit('maxDepth', ['kind' => 'object', 'class' => get_debug_type($object)]);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processObjectWithReflection(object $object, array $rules): object
    {
        $copy = new stdClass();

        $this->processPublicProperties($object, $rules, $copy);
        $this->processPrivateProperties($object, $rules, $copy);

        return $copy;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     *
     * @return array<string, mixed>
     */
    private function processObjectAsArray(object $object, array $rules): array
    {
        return $this->processProperties(get_object_vars($object), $rules);
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processPrivateProperties(object $object, array $rules, object $copy): void
    {
        $ref = $this->getReflection($object);

        foreach ($ref->getProperties() as $prop) {
            if ($prop->isPublic()) {
                continue;
            }

            if ($prop->isStatic()) {
                continue;
            }

            $name = $prop->getName();
            $propValue = $prop->getValue($object);

            if ($this->hitNodeLimit('object_property', $name)) {
                $copy->{$name} = $this->overflowPlaceholder ?? $propValue;

                continue;
            }

            $copy->{$name} = $this->maskValue($name, $propValue, $rules);
        }
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processPublicProperties(object $object, array $rules, object $copy): void
    {
        foreach ($this->processProperties(get_object_vars($object), $rules) as $name => $value) {
            $copy->{$name} = $value;
        }
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function maskValue(string $key, mixed $value, array $rules): mixed
    {
        $rule = $rules[$key] ?? null;
        if (is_scalar($value) && $rule instanceof RedactionRuleInterface) {
            return $rule->apply((string) $value, $this);
        }

        if (is_array($value) || (is_object($value) && $this->shouldProcessObject($value))) {
            $this->processValue($value, $rules);
        }

        return $value;
    }

    private function shouldStopForDepth(): bool
    {
        return null !== $this->maxDepth && $this->currentDepth >= $this->maxDepth;
    }

    private function replaceIfNeeded(mixed &$value): void
    {
        if (null !== $this->overflowPlaceholder) {
            $value = $this->overflowPlaceholder;
        }
    }

    /**
     * @param array<string, mixed> $info
     */
    private function onLimit(string $type, array $info = []): void
    {
        if (is_callable($this->onLimitExceededCallback)) {
            ($this->onLimitExceededCallback)(array_merge($info, [
                'type' => $type,
                'depth' => $this->currentDepth,
                'nodesVisited' => $this->nodesVisited,
            ]));
        }
    }

    private function checkLimitPerContainer(int &$count, int|string $key): bool
    {
        if (null !== $this->maxItemsPerContainer && $count >= $this->maxItemsPerContainer) {
            $this->onLimit('maxItemsPerContainer', ['key' => $key]);

            return true;
        }
        ++$count;

        return false;
    }

    private function hitNodeLimit(string $kind, int|string $key): bool
    {
        if (++$this->nodesVisited > ($this->maxTotalNodes ?? PHP_INT_MAX)) {
            $this->onLimit('maxTotalNodes', ['kind' => $kind, 'key' => $key]);

            return true;
        }

        return false;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processChild(int|string $key, mixed &$item, array $rules): void
    {
        if ($this->hitNodeLimit('node', $key)) {
            $this->replaceIfNeeded($item);

            return;
        }

        if (is_scalar($item) && isset($rules[$key]) && $rules[$key] instanceof RedactionRuleInterface) {
            $item = $rules[$key]->apply((string) $item, $this);

            return;
        }

        if (is_array($item) || is_object($item)) {
            $this->processValue($item, $rules);
        }
    }

    /**
     * @return array<string, RedactionRuleInterface>
     */
    private function loadDefaultRules(): array
    {
        return DefaultRules::getAll();
    }

    private function resetState(): void
    {
        $this->currentDepth = 0;
        $this->nodesVisited = 0;
        $this->seenObjects = ObjectViewModeEnum::Skip === $this->objectViewMode
            ? null
            : new SplObjectStorage();
    }

    /**
     * @template T of object
     *
     * @param T $object
     *
     * @return ReflectionClass<object>
     */
    private function getReflection(object $object): ReflectionClass
    {
        $class = $object::class;
        if (! isset(self::$reflectionCache[$class])) {
            $newReflection = new ReflectionClass($object);
            self::$reflectionCache[$class] = $newReflection;
        }

        return self::$reflectionCache[$class];
    }

    private function enterDepth(): bool
    {
        if (null !== $this->maxDepth && $this->currentDepth >= $this->maxDepth) {
            return false;
        }
        ++$this->currentDepth;

        return true;
    }

    private function leaveDepth(): void
    {
        --$this->currentDepth;
    }

    /**
     * @param iterable<string, mixed>                                    $vars
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     *
     * @return array<string, mixed>
     */
    private function processProperties(iterable $vars, array $rules): array
    {
        $result = [];
        $count = 0;

        foreach ($vars as $name => $value) {
            if ($this->checkLimitPerContainer($count, $name)) {
                break;
            }

            if ($this->hitNodeLimit('object_property', $name)) {
                $result[$name] = $this->overflowPlaceholder ?? $value;

                continue;
            }

            $result[$name] = $this->maskValue($name, $value, $rules);
        }

        return $result;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processValueCopy(mixed $value, array $rules): mixed
    {
        if (is_array($value)) {
            if (! $this->enterDepth()) {
                $this->onLimit('maxDepth', ['kind' => 'array']);

                return $this->overflowPlaceholder ?? $value;
            }

            $result = $this->processContainerCopy($value, $rules);
            $this->leaveDepth();

            return $result;
        }

        if (is_object($value) && $this->shouldProcessObject($value)) {
            return $this->processObjectCopy($value, $rules);
        }

        return $value;
    }

    /**
     * @param array<string, mixed>                                       $array
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     *
     * @return array<string, mixed>
     */
    private function processContainerCopy(array $array, array $rules): array
    {
        $result = null;
        $count = 0;

        foreach ($array as $key => $item) {
            if ($this->checkLimitPerContainer($count, $key)) {
                continue;
            }

            $processedItem = $this->processChildCopy($key, $item, $rules);

            if ($processedItem !== $item && null === $result) {
                $result = $array;
            }

            if (null !== $result) {
                $result[$key] = $processedItem;
            }
        }

        return $result ?? $array;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processObjectCopy(object $object, array $rules): mixed
    {
        if (ObjectViewModeEnum::Skip === $this->objectViewMode) {
            return sprintf('[object %s]', get_debug_type($object));
        }

        if ($this->shouldSkipObject($object)) {
            return $this->overflowPlaceholder ?? $object;
        }

        $this->seenObjects?->attach($object, true);
        ++$this->currentDepth;

        try {
            if (ObjectViewModeEnum::PublicArray === $this->objectViewMode) {
                return $this->processPublicArrayCopy(get_object_vars($object), $rules);
            }

            return $this->processFullObjectCopy($object, $rules);
        } finally {
            --$this->currentDepth;
        }
    }

    /**
     * @param array<string, mixed>                                       $publicProps
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     *
     * @return array<string, mixed>
     */
    private function processPublicArrayCopy(array $publicProps, array $rules): array
    {
        return $this->processPropertiesCopy($publicProps, $rules);
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processFullObjectCopy(object $object, array $rules): object
    {
        $ref = $this->getReflection($object);
        $originalVars = get_object_vars($object);
        $processedVars = $this->processPropertiesCopy($originalVars, $rules);

        $changed = $processedVars !== $originalVars;

        foreach ($ref->getProperties() as $prop) {
            if ($prop->isPublic()) {
                continue;
            }

            if ($prop->isStatic()) {
                continue;
            }

            $name = $prop->getName();
            $val = $prop->getValue($object);
            $processedVal = $this->maskValueCopy($name, $val, $rules);

            if ($processedVal !== $val) {
                $changed = true;
            }

            $processedVars[$name] = $processedVal;
        }

        if (! $changed) {
            return $object;
        }

        $copy = new stdClass();
        foreach ($processedVars as $name => $val) {
            $copy->{$name} = $val;
        }

        return $copy;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processChildCopy(int|string $key, mixed $item, array $rules): mixed
    {
        if ($this->hitNodeLimit('node', $key)) {
            return $this->overflowPlaceholder ?? $item;
        }

        if (is_scalar($item) && isset($rules[$key]) && $rules[$key] instanceof RedactionRuleInterface) {
            return $rules[$key]->apply((string) $item, $this);
        }

        if (is_array($item) || (is_object($item) && $this->shouldProcessObject($item))) {
            return $this->processValueCopy($item, $rules);
        }

        return $item;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function maskValueCopy(string $key, mixed $value, array $rules): mixed
    {
        $rule = $rules[$key] ?? null;
        if (is_scalar($value) && $rule instanceof RedactionRuleInterface) {
            return $rule->apply((string) $value, $this);
        }

        if (is_array($value) || (is_object($value) && $this->shouldProcessObject($value))) {
            return $this->processValueCopy($value, $rules);
        }

        return $value;
    }

    /**
     * @param iterable<string, mixed>                                    $vars
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     *
     * @return array<string, mixed>
     */
    private function processPropertiesCopy(iterable $vars, array $rules): array
    {
        $result = null;
        $count = 0;

        foreach ($vars as $name => $value) {
            if ($this->checkLimitPerContainer($count, $name)) {
                break;
            }

            if ($this->hitNodeLimit('object_property', $name)) {
                $processed = $this->overflowPlaceholder ?? $value;
            } else {
                $processed = $this->maskValueCopy((string) $name, $value, $rules);
            }

            if ($processed !== $value && null === $result) {
                $result = is_array($vars) ? $vars : iterator_to_array($vars);
            }

            if (null !== $result) {
                $result[$name] = $processed;
            }
        }

        return $result ?? (is_array($vars) ? $vars : iterator_to_array($vars));
    }
}
