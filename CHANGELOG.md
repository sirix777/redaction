# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - Unreleased
### Added
- Added `sirix/container-resolver` as a runtime dependency for strict PSR-11 factory configuration.
- Added immutable `RedactorOptions` for constructor/factory-based bootstrap configuration.
- Added `RedactionRuleContextInterface` as the minimal rule-level context passed to redaction rules.
- Added per-call redaction context to make shared `Redactor` instances safer in long-running applications.
- Added tests for traversal limits, object cycles, array self-reference guarded by `maxDepth`, object view modes, default rules, Mezzio ConfigProvider, long-running/reentrant calls, strict factory config, length limits, and Monolog processor scope.

### Changed
- **BC break:** Replaced mutating `set*()` configuration methods with immutable `with*()` methods that return a configured copy.
- **BC break:** Narrowed `RedactorInterface` to the service contract only: `redact(mixed $rawData): mixed`. Fluent configuration methods and option getters are available on the concrete `Redactor`.
- **BC break:** `RedactionRuleInterface::apply()` now receives `RedactionRuleContextInterface` instead of `RedactorInterface`; custom rules can access rule-level options but no longer depend on the full redactor service.
- **BC break:** `RedactorFactory` now reads config strictly. Existing invalid values throw container/config exceptions instead of being silently ignored or coerced.
- **BC break:** Numeric strings are no longer accepted for integer options such as `length_limit`, `max_depth`, `max_items_per_container`, and `max_total_nodes`.
- **BC break:** `maxItemsPerContainer` now truncates containers and appends the overflow placeholder instead of continuing to iterate over all remaining items.
- **BC break:** The default overflow placeholder is now `'...'`; pass `null` to `withOverflowPlaceholder()` or config `overflow_placeholder` to omit overflow markers. Exceeded branches are replaced with `null` instead of raw unprocessed data when placeholders are disabled.
- **BC break:** Mask templates must contain exactly one plain `%s`; width specifiers and multiple placeholders are rejected.
- **BC break:** `ObjectViewModeEnum::Copy` now consistently returns a plain `stdClass` projection instead of returning the original object when no properties changed.
- `maxTotalNodes` now stops traversal after the first exceeded node instead of continuing to scan all remaining nodes.
- Matched rule exceptions now fail closed by replacing the value with the overflow placeholder, or `null` when placeholders are disabled, instead of aborting redaction.
- Limit callback exceptions are now ignored because callbacks are best-effort diagnostics and must not make redaction fail open.
- Built-in masking rules now respect `lengthLimit`; `FullMaskRule`, `OffsetRule`, and `StartEndRule` avoid creating unnecessarily large intermediate masks when a limit is configured.
- `RedactorFactory` now builds a validated `RedactorOptions` instance before creating `Redactor`.
- `ObjectViewModeEnum` can now be configured by enum instance or string backed value via the strict factory.
- `SharedRuleFactory` and `DefaultRules` no longer keep static rule caches; default and helper rules are created fresh to avoid shared mutable state in long-running processes.

### Removed
- Removed legacy mutable traversal state from shared `Redactor` instances.
- Removed the static reflection cache from `Redactor` to avoid unbounded growth in long-running workers.

### Documented
- Documented production safety limits, strict DI configuration, context-only Monolog redaction, top-level scalar behavior, and long-running application guidance.

## [1.3.1] - 2025-11-23
### Changed
- Platform: Bumped supported PHP versions to 8.2–8.5; dropped 8.1.
- Tooling: Updated PHPUnit to 11.x and refreshed phpunit.xml with detailed reporting and cache settings.
- Tooling: Advanced Rector level to PHP 8.2; PHP-CS-Fixer config updated to PHP 8.2 rules and allow unsupported PHP version.
- Code quality: Marked small immutable components as `readonly` classes (`FixedValueRule`, `OffsetRule`, `Bridge\Monolog\RedactorProcessor`) to reflect intent and improve safety. No public API changes.
- Docs: README updated to reflect new PHP version support.

## [1.3.0] - 2025-10-24
### Changed
- Core: Implemented copy-on-write traversal in Redactor to reduce memory usage.
  - No copying by default when no redaction occurs.
  - Lazy copying for arrays and objects; copies are created only on first change.
  - Preserved immutability and maintained public API behavior.
  - Depth/item/node limits and cycle detection maintained; overflow placeholder respected.
- Docs: README updated with memory optimization notes and guidance.

## [1.2.0] - 2025-10-23
### Added
- Shared rule factory helpers via `Sirix\Redaction\Rule\Factory\SharedRuleFactory` for convenient, cached creation of common rules.
- Additional tests covering nested keys, object reflection, rule behaviors, and Monolog integration.

### Changed
- Expanded and refined default rule set for common sensitive fields. See `src/Rule/Default/DefaultRules.php`.
- Improved masking behavior and edge-case handling in rules (e.g., Email, Name, Phone, Offset) and documentation examples.
- README updated with detailed configuration, rule factory usage, and operational notes.

## [1.1.0] - 2025-10-23
### Added
- Mezzio/Laminas integration via `Sirix\Redaction\Bridge\Mezzio\ConfigProvider` for automatic container wiring.
- PSR‑11 factory `Sirix\Redaction\Factory\RedactorFactory` to build `Redactor` from container config.
- Example configuration file at `src/Bridge/Mezzio/config-example/redactor.config.global.php`.
- Composer extra `laminas.config-provider` entry for auto‑discovery in Laminas applications.
- README section covering framework/DI usage and configuration options.
- Tests for the PSR‑11 factory.

### Changed
- Composer metadata: suggestions for Mezzio integration and keywords improved.
- Documentation improvements and examples.

## [1.0.0] - 2025-10-22
### Added
- Initial public release of `sirix/redaction`.
- Core Redactor with recursive redaction for arrays, objects, and iterables.
- Built-in rules: `StartEndRule`, `EmailRule`, `PhoneRule`, `FullMaskRule`, `FixedValueRule`, `NameRule`, `NullRule`.
- Default rule set for common sensitive fields, with ability to disable and use custom rules only.
- Configuration options: replacement character, mask template, length limit, object view modes (Copy, PublicArray, Skip), traversal limits, overflow placeholder, and limit-exceeded callback.
- Monolog bridge: `Sirix\Redaction\Bridge\Monolog\RedactorProcessor` for seamless log redaction (optional; requires `monolog/monolog` ^3.0).
- PHP 8.1–8.4 support.
- PHPUnit test suite and QA tooling (PHPStan, PHP-CS-Fixer, Rector).

