<?php

namespace Sjshovan\RulesetValidation\Support;

use Sjshovan\RulesetValidation\Abstracts\BaseModelRuleset;
use Sjshovan\RulesetValidation\Abstracts\BaseRuleset;
use Symfony\Component\Filesystem\Path;

/**
 * Class Package
 *
 * Central utility class for accessing package-level configuration, conventions,
 * stub rendering, and path resolution within the laravel-ruleset-validation package.
 *
 * Provides typed helpers for retrieving configured paths, naming conventions,
 * and behavioral flags. Also handles stub file loading and rendering for code
 * generation commands such as `make:ruleset`.
 *
 * This class is intended for internal use only and is not meant to be extended.
 * It supports customization through the `ruleset-validation.php` config file.
 *
 * @internal
 */
final class Package
{
    // @formatter:off

    //--= info =--

    public const NAME = 'laravel-ruleset-validation';
    public const VENDOR = 'sjshovan';
    public const REPOSITORY = 'sjshovan/laravel-ruleset-validation';
    public const VENDOR_EMAIL = 'sjshovan@gmail.com';

    //--= filenames =--

    public const FILENAME_CONFIG = 'ruleset-validation';

    //--= stubs =--

    public const STUB_RULESET = 'ruleset.stub';
    public const STUB_MODEL_RULESET = 'model-ruleset.stub';

    //--= classnames =--

    public const CLASSNAME_RULESET_ABSTRACT = 'RulesetAbstract';
    public const CLASSNAME_MODEL_RULESET_ABSTRACT = 'ModelRulesetAbstract';

    // @formatter:on

    //--= config =--

    private static function config(string $key, mixed $default = null): mixed
    {
        $fileName = self::FILENAME_CONFIG;

        return config("{$fileName}.{$key}", $default);
    }

    //--= config/types =--

    private static function paths(string $key, mixed $default = null): mixed
    {
        return self::config("paths.$key", $default);
    }

    private static function convention(string $key, mixed $default = null): mixed
    {
        return self::config("convention.$key", $default);
    }

    private static function behavior(string $key, mixed $default = null): mixed
    {
        return self::config("behavior.$key", $default);
    }

    //--= config/paths =--

    public static function getRulesetOutputPath(): string
    {
        return self::paths('ruleset_output', app_path('Rulesets'));
    }

    public static function getModelDiscoveryPaths(): array
    {
        return self::paths('model_discovery', [app_path('Models')]);
    }

    //--= config/convention =--

    public static function getBaseRulesetAbstractClass(): string
    {
        return self::convention('base_abstracts.ruleset', BaseRuleset::class);
    }

    public static function getBaseModelRulesetAbstractClass(): string
    {
        return self::convention('base_abstracts.model_ruleset', BaseModelRuleset::class);
    }

    public static function getRulesetSuffix(): string
    {
        return self::convention('ruleset_suffix', 'Ruleset');
    }

    //--= config/behavior =--

    public static function usesFactoryDecorator(): bool
    {
        return self::behavior('factory_decorator', false);
    }

    public static function usesFactoryConcrete(): bool
    {
        return self::behavior('factory_concrete', false);
    }

    //--= config/behavior/convention =--

    public static function usesRulesetSuffix(): bool
    {
        return self::behavior('convention.ruleset_suffix', true);
    }

    public static function usesIntermediateAbstracts(): bool
    {
        return self::behavior('convention.intermediate_abstracts', false);
    }

    //--= helpers/paths =--

    public static function basePath(string $path = ''): string
    {
        $base = dirname(__DIR__, 2);

        return $path ? Path::join($base, $path) : $base;
    }

    public static function configPath(bool $includeFile = false): string
    {
        $path = self::basePath('config');

        return $includeFile ? $path : dirname($path);
    }

    public static function stubsPath(string $stub = ''): string
    {
        $base = self::basePath('stubs');

        return $stub ? Path::join($base, $stub) : $base;
    }

    //--= helpers/stubs =--

    public static function getStubContents(string $stub): string
    {
        $contents = @file_get_contents(self::stubsPath($stub));

        if ($contents === false) {
            throw new \RuntimeException("Stub not found: {$stub}");
        }

        return $contents;
    }

    public static function renderStub(string $stub, array $replacements = []): string
    {
        $contents = self::getStubContents($stub);

        return $replacements ? strtr($contents, $replacements) : $contents;
    }
}