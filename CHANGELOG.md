# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

