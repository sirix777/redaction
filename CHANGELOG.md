# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.3.0] - 24/10/2025
### Changed
- Core: Implemented copy-on-write traversal in Redactor to reduce memory usage.
  - No copying by default when no redaction occurs.
  - Lazy copying for arrays and objects; copies are created only on first change.
  - Preserved immutability and maintained public API behavior.
  - Depth/item/node limits and cycle detection maintained; overflow placeholder respected.
- Docs: README updated with memory optimization notes and guidance.

## [1.2.0] - 23/10/2025
### Added
- Shared rule factory helpers via `Sirix\Redaction\Rule\Factory\SharedRuleFactory` for convenient, cached creation of common rules.
- Additional tests covering nested keys, object reflection, rule behaviors, and Monolog integration.

### Changed
- Expanded and refined default rule set for common sensitive fields. See `src/Rule/Default/DefaultRules.php`.
- Improved masking behavior and edge-case handling in rules (e.g., Email, Name, Phone, Offset) and documentation examples.
- README updated with detailed configuration, rule factory usage, and operational notes.

## [1.1.0] - 23/10/2025
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

## [1.0.0] - 22/10/2025
### Added
- Initial public release of `sirix/redaction`.
- Core Redactor with recursive redaction for arrays, objects, and iterables.
- Built-in rules: `StartEndRule`, `EmailRule`, `PhoneRule`, `FullMaskRule`, `FixedValueRule`, `NameRule`, `NullRule`.
- Default rule set for common sensitive fields, with ability to disable and use custom rules only.
- Configuration options: replacement character, mask template, length limit, object view modes (Copy, PublicArray, Skip), traversal limits, overflow placeholder, and limit-exceeded callback.
- Monolog bridge: `Sirix\Redaction\Bridge\Monolog\RedactorProcessor` for seamless log redaction (optional; requires `monolog/monolog` ^3.0).
- PHP 8.1–8.4 support.
- PHPUnit test suite and QA tooling (PHPStan, PHP-CS-Fixer, Rector).

