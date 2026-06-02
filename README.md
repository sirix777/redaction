# Redaction

[![Latest Stable Version](http://poser.pugx.org/sirix/redaction/v)](https://packagist.org/packages/sirix/redaction) [![Total Downloads](http://poser.pugx.org/sirix/redaction/downloads)](https://packagist.org/packages/sirix/redaction) [![Latest Unstable Version](http://poser.pugx.org/sirix/redaction/v/unstable)](https://packagist.org/packages/sirix/redaction) [![License](http://poser.pugx.org/sirix/redaction/license)](https://packagist.org/packages/sirix/redaction) [![PHP Version Require](http://poser.pugx.org/sirix/redaction/require/php)](https://packagist.org/packages/sirix/redaction)

A PHP library for data redaction, masking, and sanitization with optional Monolog and Mezzio/Laminas integration.

This library provides a small core that can redact sensitive data in arrays and objects using pluggable rules. You can use it anywhere in your app (HTTP payloads, DTOs, database debug dumps, etc.), and optionally plug it into Monolog via a tiny bridge. For framework users, a PSR‑11 factory and a Mezzio/Laminas ConfigProvider are included.

- PHP 8.2–8.5
- Optional: Monolog ^3.0 (for the bridge only)
- Optional: Mezzio/Laminas (for auto‑wiring via ConfigProvider)
- License: MIT

## Installation

Install the core library:

```bash
composer require sirix/redaction
```

## Quick start (core library)

```php
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorOptions;
use Sirix\Redaction\Rule\StartEndRule;
use Sirix\Redaction\Rule\EmailRule;
use Sirix\Redaction\Rule\NameRule;
use Sirix\Redaction\Enum\ObjectViewModeEnum;

$redactor = new Redactor(
    customRules: [
        // Overwrite or add rules per key; custom rules override defaults when keys overlap
        // Option 1 — direct instantiation:
        'card_number' => new StartEndRule(6, 4),
        // Option 2 — via factory helper (equivalent):
        // 'card_number' => SharedRuleFactory::startEnd(6, 4),
        'email' => new EmailRule(),
        // Factory helper (equivalent):
        // 'email' => SharedRuleFactory::email(),
        'name'  => new NameRule(),
        // Factory helper (equivalent):
        // 'name' => SharedRuleFactory::name(),
    ],
    options: new RedactorOptions(
        objectViewMode: ObjectViewModeEnum::Copy,
        maxDepth: 8,
        maxItemsPerContainer: 100,
        maxTotalNodes: 5000,
    ),
);

// Note: By default, Redactor loads a set of sensible default rules.
// To disable them and use only your own custom rules, pass useDefaultRules: false
// e.g. $redactor = new Redactor(customRules: [...], useDefaultRules: false);

// Optional tuning can also be done fluently.
// Redactor is immutable in 2.0: with* methods return a configured copy.
// Always assign the returned instance.
$redactor = $redactor
    ->withReplacement('*')                 // character used to build masks
    ->withTemplate('%s')                   // safe sprintf template with exactly one plain %s
    ->withLengthLimit(null)                // max length of the resulting masked value (null = unlimited)
    ->withObjectViewMode(ObjectViewModeEnum::Copy) // Copy | PublicArray | Skip
    ->withMaxDepth(null)                   // limit recursion depth for arrays/objects (null = unlimited)
    ->withMaxItemsPerContainer(null)       // limit items per array/object (null = unlimited)
    ->withMaxTotalNodes(null)              // global cap on visited nodes (null = unlimited)
    ->withOnLimitExceededCallback(function (array $info): void {
        // Called when a limit is hit or a cycle is detected; inspect $info if desired
        // e.g., error_log('Redaction limit: '.json_encode($info));
    })
    ->withOverflowPlaceholder('...');      // default is '...', pass null to omit overflow markers

$payload = [
    'card_number' => '1234567890123456',
    'user' => [
        'email' => 'john.doe@example.com',
        'name'  => 'John Doe',
        'phone' => '+44123456789012',
    ],
];

$redacted = $redactor->redact($payload);
```

## Optional: Monolog integration

```php
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Bridge\Monolog\RedactorProcessor;

$logger = new Logger('app');
$logger->pushHandler(new StreamHandler('php://stdout'));

$redactor = new Redactor(); // you may pass custom rules here as in the core example
$processor = new RedactorProcessor($redactor);
$logger->pushProcessor($processor);

$logger->info('User checkout', [
    'card_number' => '1234567890123456',
    'user' => [
        'email' => 'john.doe@example.com',
        'name'  => 'John Doe',
        'phone' => '+44123456789012',
    ],
]);
```

Example output (stdout):

```
[info] app: User checkout {"card_number":"123456******3456","user":{"email":"joh****@example.com","name":"J*** D**","phone":"+4412****12"}}
```

Note: Exact output format depends on your handler/formatter. The masking shown reflects the default rules plus the ones configured above.

The Monolog processor redacts `LogRecord::context` only. It does not redact `message` or `extra` by default.

## Framework/DI integration (Mezzio/Laminas, PSR‑11)

This package ships with:

- A PSR‑11 factory: `Sirix\Redaction\Factory\RedactorFactory`
- A Mezzio/Laminas config provider: `Sirix\Redaction\Bridge\Mezzio\ConfigProvider`

With Laminas/Mezzio, you can wire the service automatically via the ConfigProvider. Add the provider to your application config if not discovered automatically:

```php
// config/config.php or a module config
return [
    'dependencies' => [
        // You can omit this if you use the provided ConfigProvider
        'aliases' => [
            Sirix\Redaction\RedactorInterface::class => Sirix\Redaction\Redactor::class,
        ],
        'factories' => [
            Sirix\Redaction\Redactor::class => Sirix\Redaction\Factory\RedactorFactory::class,
        ],
    ],

    // Redactor configuration
    'redactor' => [
        'options' => [
            // Custom rules (same structure as passing to the constructor)
            'rules' => [
                'card_number' => new Sirix\Redaction\Rule\StartEndRule(6, 4),
            ],

            // Whether to load built‑in default rules (bool, default: true)
            'use_default_rules' => true,

            // Core options (all optional, read strictly; no scalar coercion)
            'replacement' => '*',                 // string
            'template' => '%s',                   // string, exactly one plain %s placeholder
            'length_limit' => null,               // int|null, numeric strings are invalid
            'object_view_mode' => 'copy',         // enum instance or: copy|public_array|skip
            'max_depth' => null,                  // int|null, numeric strings are invalid
            'max_items_per_container' => null,    // int|null, numeric strings are invalid
            'max_total_nodes' => null,            // int|null, numeric strings are invalid
            'on_limit_exceeded_callback' => null, // callable|null
            'overflow_placeholder' => '...',      // string|null, default: '...'
        ],
    ],
];
```

Then type‑hint `RedactorInterface` in your services/controllers, and let the container inject it. In 2.0, this interface is intentionally small and exposes only `redact()`:

```php
use Sirix\Redaction\RedactorInterface;

final class MyService
{
    public function __construct(private RedactorInterface $redactor) {}
}
```

If you are not using Mezzio/Laminas, register the factory in your PSR‑11 container of choice, passing the `redactor.options` structure as shown above.

The PSR-11 factory uses `sirix/container-resolver` and reads configuration strictly. Existing invalid values throw configuration/container exceptions instead of being silently ignored or coerced. For example, use `5000`, not `'5000'`, for integer limits.

## Production safety

When redacting untrusted or large payloads, especially in logging pipelines, configure traversal limits:

```php
$redactor = $redactor
    ->withMaxDepth(8)
    ->withMaxItemsPerContainer(100)
    ->withMaxTotalNodes(5000)
    ->withOverflowPlaceholder('...');
```

Without limits, the redactor walks the full input structure. When `maxItemsPerContainer` is exceeded, containers are truncated and an overflow placeholder is appended (`'...'` by default). When `maxTotalNodes` is exceeded, traversal stops after the first exceeded node and any remaining siblings are omitted/truncated.

Object cycles are detected in object-processing modes. PHP array reference cycles should be guarded with `maxDepth`.

## Long-running applications

The redactor is safe to reuse as a shared service when it is fully configured at bootstrap time. In 2.0, runtime traversal state is kept per `redact()` call, and configuration is represented by immutable `RedactorOptions`. Fluent `with*` methods are convenience helpers that return a configured copy; calling them without assigning the return value leaves the original instance unchanged.

Do not store request/job-specific closures on a shared redactor. Limit callbacks configured on shared services should be stateless or backed by long-lived services. If request-specific behavior is required, create a separate configured instance/copy for that request or job.

For untrusted payloads in RoadRunner, Swoole/OpenSwoole, ReactPHP/Amp, queue workers, or persistent Mezzio/Laminas apps, configure traversal limits.

## Memory optimization

The Redactor uses a copy-on-write traversal strategy:

- No copying by default for unchanged scalars/arrays and skipped objects: When no rules apply to an array branch, the original array branch is returned as-is, avoiding unnecessary copies.
- Lazy array copying: Arrays are copied only when a change is first detected. A target array is created only upon the first modified element.
- Explicit object projection: In Copy mode, objects are projected to a plain `stdClass` copy. In PublicArray mode, objects are projected to an array of public properties. The default Skip mode avoids traversing object properties.
- Immutability preserved: The top-level input you pass to redact() is never mutated. When changes occur, they are applied to the lazily created copies.
- Limits and cycles: Depth/item/node limits and object cycle detection are applied during traversal. When a limit is hit, the overflow placeholder is used for truncated parts by default.

This reduces peak memory usage when little or no redaction occurs while keeping input data immutable.

## How it works

- The redactor recursively walks through scalars in your data and applies a rule when a key matches.
- A top-level scalar has no key and is returned unchanged.
- For arrays, the same flat rules map is used at every depth; rules match by key name regardless of nesting. Nested per-path rule maps are not supported.
- Objects are handled according to an object view mode (default: Skip):
  - Copy: returns a plain stdClass copy and recursively processes both public and non-static private/protected properties.
  - PublicArray: returns an array of public properties only.
  - Skip: replaces the object with a compact string like "[object Foo\\Bar]" and does not traverse properties.
- Object cycles are detected. When depth/item/node limits are exceeded, an optional callback is invoked and the overflow placeholder is used to replace or mark truncated parts. If `overflowPlaceholder` is `null`, exceeded branches are replaced with `null` and truncated containers omit the marker instead of returning raw unprocessed data. Limit callbacks are best-effort: exceptions thrown by callbacks are ignored so redaction does not fail open. For array reference cycles, configure `maxDepth`.

## Default rules

By default, the core `Redactor` loads a curated set of rules for common sensitive fields (card numbers/PAN, CVV, expiry, names, emails, phone, IPs, addresses, tokens, 3‑D Secure fields, etc.). See `src/Rule/Default/DefaultRules.php` for the complete list.

To disable default rules and use only your own:

```php
$redactor = new Redactor(customRules: [], useDefaultRules: false);
```

## Built‑in rule types

These rules live under `Sirix\Redaction\Rule` and can be created directly or via factory helpers:

- `StartEndRule($visibleStart, $visibleEnd)` - available via `SharedRuleFactory::startEnd($visibleStart, $visibleEnd)`. Masks the middle part of a string, keeping the given number of characters at the start/end.
- `EmailRule` available via `SharedRuleFactory::email()`. Masks the local part of an email, keeping the first 3 characters and the full domain.
- `PhoneRule` available via `SharedRuleFactory::phone()`. Masks digits in the middle of a phone number, keeping the first 4 and last 2 digits when possible.
- `FullMaskRule` available via `SharedRuleFactory::fullMask()`. Replaces the entire value with the replacement character(s).
- `FixedValueRule($replacement)` available via `SharedRuleFactory::fixedValue($replacement)`. Always outputs the provided constant string (e.g., `*` or `**/****`).
- `NameRule` available via `SharedRuleFactory::name()`. Masks personal names leaving just initials and/or a few characters as defined by the rule.
- `NullRule` available via `SharedRuleFactory::null()`. Sets the value to null.
- `OffsetRule($offset)` available via `SharedRuleFactory::offset($offset)`. Masks the first N characters (from the start) according to the offset.

### Shared rule factory (optional)

For convenience, you can use factory helpers:

```php
use Sirix\Redaction\Rule\Factory\SharedRuleFactory;
use Sirix\Redaction\Redactor;

$redactor = new Redactor([
    'card_number' => SharedRuleFactory::startEnd(6, 4),
    'email'       => SharedRuleFactory::email(),
    'phone'       => SharedRuleFactory::phone(),
]);
```

Default rules and helper methods return fresh rule instances, avoiding static rule caches in long-running processes.

If you need a custom masking strategy, implement `RedactionRuleInterface`. Rules receive a minimal `RedactionRuleContextInterface` with rule-level options (`replacement`, `template`, and `lengthLimit`) instead of the full redactor service:

```php
use Sirix\Redaction\RedactionRuleContextInterface;
use Sirix\Redaction\Rule\RedactionRuleInterface;

use function str_repeat;

final class MyRule implements RedactionRuleInterface
{
    public function apply(string $value, RedactionRuleContextInterface $context): ?string
    {
        if ('' === $value) {
            return null;
        }

        return str_repeat($context->getReplacement(), 3);
    }
}
```

## Redactor options

For bootstrap/container configuration, prefer immutable `RedactorOptions`:

```php
use Sirix\Redaction\Enum\ObjectViewModeEnum;
use Sirix\Redaction\Redactor;
use Sirix\Redaction\RedactorOptions;

$redactor = new Redactor(
    options: new RedactorOptions(
        replacement: '*',
        template: '%s',
        objectViewMode: ObjectViewModeEnum::Copy,
        maxDepth: 8,
        maxItemsPerContainer: 100,
        maxTotalNodes: 5000,
        overflowPlaceholder: '...',
    ),
);
```

For local variations, use immutable `with*` methods:

- `withReplacement(string $char)`: character(s) used to construct masks (default `*`).
- `withTemplate(string $template)`: a safe `sprintf` template applied to the mask string (default `'%s'`). It must contain exactly one plain `%s`; width specifiers and multiple placeholders are rejected.
- `withLengthLimit(?int $limit)`: if set, truncates built-in rule output to at most this length. Mask-building rules avoid creating large intermediate masks when this limit is set.
- `withObjectViewMode(ObjectViewModeEnum $mode)`: how to represent objects during redaction. Defaults to `ObjectViewModeEnum::Skip`.
- `withMaxDepth(?int $depth)`: maximum recursion depth for arrays/objects. `null` means unlimited.
- `withMaxItemsPerContainer(?int $count)`: limit the number of items per array/object. When exceeded, the container is truncated and an overflow placeholder is appended if configured.
- `withMaxTotalNodes(?int $count)`: global cap on visited nodes (array elements, object properties, child nodes). Once exceeded, traversal stops after the first exceeded node. `null` means unlimited.
- `withOnLimitExceededCallback(?callable $cb)`: callback invoked when any limit is hit, a cycle is detected, or a matched rule throws. Receives an info array with keys like `type`, `depth`, `nodesVisited`, and context-specific fields. Callback exceptions are ignored to keep redaction fail-closed.
- `withOverflowPlaceholder(?string $value)`: value used to replace or mark truncated parts when limits are exceeded. Defaults to `'...'`; pass `null` to omit overflow markers while replacing exceeded branches with `null`.

Notes:
- These options influence built-in rules and the core traversal/limit behavior.
- If a matched rule throws, the sensitive value is replaced with `overflowPlaceholder` or `null` when placeholders are disabled.
- Limits apply to arrays and objects uniformly; object cycles are detected to avoid infinite recursion.

## Testing & QA

This repository includes a PHPUnit test suite and tooling configs.

- Run tests: `composer test`
- Static analysis: `composer phpstan`
- Code style check: `composer cs-check`
- Auto‑fix style: `composer cs-fix`

## Versioning

- PHP: ~8.2.0 || ~8.3.0 || ~8.4.0 || ~8.5.0
- Optional Monolog: ^3.0 (for the bridge)

## License

MIT © Sirix
