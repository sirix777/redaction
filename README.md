# Redaction

A PHP library for data redaction, masking, and sanitization with optional Monolog integration.

This library provides a small core that can redact sensitive data in arrays, objects, and iterables using pluggable rules. You can use it anywhere in your app (HTTP payloads, DTOs, database debug dumps, etc.), and—optionally—plug it into Monolog via a tiny bridge.

- PHP 8.1–8.4
- Optional: Monolog ^3.0 (for the bridge only)
- License: MIT

## Installation

Install the core library:

```bash
composer require sirix/redaction
```

If you want to use Monolog integration, also require Monolog (if you don't have it already):

```bash
composer require monolog/monolog
```

## Quick start (core library)

```php
use Sirix\Redaction\Redactor;
use Sirix\Redaction\Rule\StartEndRule;
use Sirix\Redaction\Rule\EmailRule;
use Sirix\Redaction\Rule\NameRule;
use Sirix\Redaction\Enum\ObjectViewModeEnum;

$redactor = new Redactor([
    // Overwrite or add rules per key
    'card_number' => new StartEndRule(6, 4),
    'user' => [ // nested structure rules
        'email' => new EmailRule(),
        'name'  => new NameRule(),
    ],
]);

// Note: By default, Redactor loads a set of sensible default rules.
// To disable them and use only your own custom rules, pass useDefaultRules: false
// e.g. $redactor = new Redactor(customRules: [...], useDefaultRules: false);

// Optional tuning (all available)
// You can call setters individually or chain them — all setters are chainable and return RedactorInterface.
$redactor->setReplacement('*');                 // character used to build masks
$redactor->setTemplate('%s');                   // sprintf template for mask; e.g. '[%s]' to wrap
$redactor->setLengthLimit(null);                // max length of the resulting masked value (null = unlimited)
$redactor->setObjectViewMode(ObjectViewModeEnum::Copy); // how to treat objects (Copy | PublicArray | Skip)
$redactor->setMaxDepth(null);                   // limit recursion depth for arrays/objects (null = unlimited)
$redactor->setMaxItemsPerContainer(null);       // limit items processed per array/object (null = unlimited)
$redactor->setMaxTotalNodes(null);              // global cap on visited nodes (null = unlimited)
$redactor->setOnLimitExceededCallback(function (array $info): void {
    // Called when a limit is hit or a cycle is detected; inspect $info if desired
    // e.g., error_log('Redaction limit: '.json_encode($info));
});
$redactor->setOverflowPlaceholder('...');       // value to use when truncating parts due to limits

// Or chain them:
$redactor
    ->setReplacement('*')
    ->setTemplate('%s')
    ->setLengthLimit(null)
    ->setObjectViewMode(ObjectViewModeEnum::Copy)
    ->setMaxDepth(null)
    ->setMaxItemsPerContainer(null)
    ->setMaxTotalNodes(null)
    ->setOnLimitExceededCallback(function (array $info): void {
        // Called when a limit is hit or a cycle is detected; inspect $info if desired
    })
    ->setOverflowPlaceholder('...');

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

## How it works

- The redactor recursively walks through scalars in your data and applies a rule when a key matches.
- Scalars at the top level (no key) are processed by all rules that operate on plain strings.
- For arrays, you can specify nested rule maps that apply to the child structure.
- Objects are handled according to an object view mode (default: Copy):
  - Copy: returns a plain stdClass copy and recursively processes both public and non-static private/protected properties.
  - PublicArray: returns an array of public properties only.
  - Skip: replaces the object with a compact string like "[object Foo\\Bar]".
- Cycles are detected. When depth/item/node limits are exceeded, an optional callback is invoked and, if configured, an overflow placeholder is used to replace truncated parts.

## Default rules

By default, the core `Redactor` loads a curated set of rules for common sensitive fields (card numbers/PAN, CVV, expiry, names, emails, phone, IPs, addresses, tokens, 3‑D Secure fields, etc.). See `src/Rule/Default/default_rules.php` for the complete list.

To disable default rules and use only your own:

```php
$redactor = new Redactor(customRules: [], useDefaultRules: false);
```

## Built‑in rule types

These rules live under `Sirix\Redaction\Rule` and can be combined as needed:

- StartEndRule($visibleStart, $visibleEnd): masks the middle part of a string, keeping given number of characters at the start/end.
- EmailRule: masks the local part of an email, keeping the first 3 characters and the full domain.
- PhoneRule: masks digits in the middle of a phone number, keeping the first 4 and last 2 digits when possible.
- FullMaskRule: replaces the entire value with the replacement character(s).
- FixedValueRule($replacement): always outputs the provided constant string (e.g., `*` or `**/****`).
- NameRule: masks personal names leaving just initials and/or a few characters as defined by the rule.
- NullRule: sets the value to null.

If you need a custom masking strategy, implement `RedactionRuleInterface`:

```php
use Sirix\Redaction\Rule\RedactionRuleInterface;
use Sirix\Redaction\Redactor;

final class MyRule implements RedactionRuleInterface
{
    public function apply(string $value, Redactor $redactor): ?string
    {
        // Return the masked string, or null to indicate no change
        return '***';
    }
}
```

## Redactor options

- setReplacement(string $char): character used to construct masks (default `*`).
- setTemplate(string $template): a `sprintf` template applied to the mask string (default `'%s'`). For example, `'[%s]'` wraps mask in brackets.
- setLengthLimit(?int $limit): if set, truncates the resulting masked value to at most this length.
- setObjectViewMode(ObjectViewModeEnum $mode): how to represent objects during redaction. Defaults to `ObjectViewModeEnum::Skip`.
- setMaxDepth(?int $depth): maximum recursion depth for arrays/objects. `null` means unlimited.
- setMaxItemsPerContainer(?int $count): limit the number of items processed per array/object (public props). `null` means unlimited.
- setMaxTotalNodes(?int $count): global cap on visited nodes (array elements, object properties, child nodes). `null` means unlimited.
- setOnLimitExceededCallback(?callable $cb): callback invoked when any limit is hit or a cycle is detected. Receives an info array with keys like `type`, `depth`, `nodesVisited`, and context-specific fields.
- setOverflowPlaceholder(mixed $value): value used to replace truncated parts when limits are exceeded. If `null` (default), the original unmodified value is kept for that node.

Notes:
- These options influence rules that build masks (e.g., StartEndRule, PhoneRule) and the core traversal/limit behavior.
- Limits apply to arrays and objects uniformly; cycles are detected to avoid infinite recursion.

## Testing & QA

This repository includes a PHPUnit test suite and tooling configs.

- Run tests: `composer test`
- Static analysis: `composer phpstan`
- Code style check: `composer cs-check`
- Auto‑fix style: `composer cs-fix`

## Versioning

- PHP: ~8.1.0 || ~8.2.0 || ~8.3.0 || ~8.4.0
- Optional Monolog: ^3.0 (for the bridge)

## License

MIT © Sirix
