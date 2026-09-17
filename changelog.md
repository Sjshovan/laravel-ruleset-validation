# Changelog

All notable changes to **Laravel Ruleset Validation** will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),  
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Unreleased — proposed 1.1.0

### Added

- Allow Laravel 13 and Symfony Filesystem 8 while retaining the existing Laravel 9–12 and PHP 8.2 constraints.
- Testbench 7–11 and PHPUnit 9–12 compatibility, with CI checks across Laravel 9–13 and PHP 8.2–8.5.
- Integration coverage for the default Laravel validator, optional concrete factory, and ruleset generation/listing commands.

### Changed

- Replace test annotations with portable `test_` method names and remove obsolete PHPUnit configuration.
- Use SQLite in CI, pin workflow actions, and report legacy dependency advisories separately from enforced current-framework audits.
- Document the single-main, tagged-release workflow. No public ruleset API or default factory behavior changes.

### Fixed

- Use the existing `Str::contains` API when appending generator suffixes so `make:ruleset` also works on Laravel 9.
- Construct discovery conditions using the API shared by older and newer Structure Discoverer 2.x releases.

## [v1.0.1](https://github.com/sjshovan/laravel-ruleset-validation/releases/tag/v1.0.1) - 2025-08-11

### Fixed

- Add missing `symfony/filesystem` dependency to `composer.json`.

## [v1.0.0](https://github.com/sjshovan/laravel-ruleset-validation/releases/tag/v1.0.0) - 2025-08-11

### Added

- Initial release of the `Laravel Ruleset Validation` package.
