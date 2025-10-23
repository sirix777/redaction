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

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $customRules
     */
    public function __construct(array $customRules = [], bool $useDefaultRules = true)
    {
        $this->rules = $useDefaultRules ? $this->loadDefaultRules() : [];
        foreach ($customRules as $key => $rule) {
            $this->rules[$key] = $this->validateRule($rule);
        }
    }

    public function redact(mixed $rawData): mixed
    {
        $this->resetState();
        $redactedData = $rawData;

        $this->processValue($redactedData, $this->rules);

        return $redactedData;
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
        if (is_scalar($value)) {
            return;
        }

        if (is_array($value)) {
            $this->processContainer($value, $rules);

            return;
        }

        if (is_object($value) && $this->shouldProcessObject($value)) {
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
        if ($this->shouldStopForDepth()) {
            $this->onLimit('maxDepth', ['kind' => 'array']);
            $this->replaceIfNeeded($array);

            return;
        }

        ++$this->currentDepth;
        $count = 0;

        foreach ($array as $key => &$item) {
            if ($this->checkLimitPerContainer($count, $key)) {
                continue;
            }

            $this->processChild($key, $item, $rules);
        }

        unset($item);
        --$this->currentDepth;
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
        $arr = [];
        $count = 0;

        foreach (get_object_vars($object) as $name => $propValue) {
            if ($this->checkLimitPerContainer($count, $name)) {
                break;
            }

            if ($this->hitNodeLimit('object_property', $name)) {
                $arr[$name] = $this->overflowPlaceholder ?? $propValue;

                continue;
            }

            $arr[$name] = $this->maskValue($name, $propValue, $rules);
        }

        return $arr;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function processPrivateProperties(object $object, array $rules, object $copy): void
    {
        $ref = new ReflectionClass($object);

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
        $count = 0;

        foreach (get_object_vars($object) as $name => $propValue) {
            if ($this->checkLimitPerContainer($count, $name)) {
                break;
            }

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
    private function maskValue(string $key, mixed $value, array $rules): mixed
    {
        if (is_scalar($value)) {
            return $this->maskScalar($key, $value, $rules);
        }

        if (is_array($value)) {
            return $this->maskArray($key, $value, $rules);
        }

        if (is_object($value)) {
            return $this->maskObject($key, $value, $rules);
        }

        return $value;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function maskScalar(string $key, mixed $value, array $rules): mixed
    {
        return $this->applyRulesIfScalar($key, $value, $rules);
    }

    /**
     * @param array<string, mixed>                                       $value
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     *
     * @return array<string, mixed>
     */
    private function maskArray(string $key, array $value, array $rules): array
    {
        $subRules = $rules[$key] ?? [];
        $this->processContainer($value, is_array($subRules) ? $subRules : []);

        return $value;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function maskObject(string $key, object $value, array $rules): mixed
    {
        $subRules = $rules[$key] ?? [];
        $obj = ObjectViewModeEnum::Copy === $this->objectViewMode ? clone $value : $value;

        if ($this->seenObjects?->contains($value)) {
            $this->onLimit('cycle', ['class' => get_debug_type($value)]);

            return $this->overflowPlaceholder ?? $value;
        }

        if ($this->hitNodeLimit('node', $key)) {
            return $this->overflowPlaceholder ?? $value;
        }

        $this->processValue($obj, is_array($subRules) ? $subRules : []);

        return $obj;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function hasRuleFor(string $key, array $rules): bool
    {
        return isset($rules[$key]) && $rules[$key] instanceof RedactionRuleInterface;
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $rules
     */
    private function applyRule(string $key, mixed $value, array $rules): mixed
    {
        $rule = $rules[$key];

        return $rule instanceof RedactionRuleInterface && is_scalar($value)
            ? $rule->apply((string) $value, $this)
            : $value;
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
        ++$this->nodesVisited;

        if (null !== $this->maxTotalNodes && $this->nodesVisited > $this->maxTotalNodes) {
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

        $ruleOrRules = $rules[$key] ?? null;

        if ($ruleOrRules instanceof RedactionRuleInterface) {
            match (true) {
                is_scalar($item) => $item = $ruleOrRules->apply((string) $item, $this),
                is_object($item) => $item = $this->maskObject((string) $key, $item, [(string) $key => $ruleOrRules]),
                is_array($item) => $this->processContainer($item, [(string) $key => $ruleOrRules]),
                default => null,
            };

            return;
        }

        if (! is_array($item) && ! is_object($item)) {
            return;
        }

        $this->processValue($item, is_array($ruleOrRules) ? $ruleOrRules : $rules);
    }

    /**
     * @param array<string, array<string, mixed>|RedactionRuleInterface> $localRules
     */
    private function applyRulesIfScalar(string $key, mixed $value, array $localRules): mixed
    {
        return match (true) {
            $this->hasRuleFor($key, $localRules) => $this->applyRule($key, $value, $localRules),
            $this->hasRuleFor($key, $this->rules) => $this->applyRule($key, $value, $this->rules),
            default => $value,
        };
    }

    /**
     * @return array<string, array<string, mixed>|RedactionRuleInterface>|RedactionRuleInterface
     */
    private function validateRule(mixed $rule): array|RedactionRuleInterface
    {
        if (! ($rule instanceof RedactionRuleInterface) && ! is_array($rule)) {
            throw new InvalidArgumentException('All sensitive keys must be RedactionRule or nested array');
        }

        return $rule;
    }

    /**
     * @return array<string, array<string, mixed>|RedactionRuleInterface>
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
}
