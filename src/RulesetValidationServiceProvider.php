<?php

namespace Sjshovan\RulesetValidation;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Validation\Factory as BaseFactoryContract;
use Sjshovan\RulesetValidation\Commands\RulesetListCommand;
use Sjshovan\RulesetValidation\Commands\RulesetMakeCommand;
use Sjshovan\RulesetValidation\Contracts\Factory as RulesetFactoryContract;
use Sjshovan\RulesetValidation\Contracts\RuleBuilder as RuleBuilderContract;
use Sjshovan\RulesetValidation\Factory as RulesetFactory;
use Spatie\LaravelPackageTools\Commands\InstallCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Sjshovan\RulesetValidation\Support\Package as RulesetValidation;

/**
 * Configure the package using Spatie's package tools.
 *
 * @see https://github.com/spatie/laravel-package-tools
 */
class RulesetValidationServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name(RulesetValidation::NAME)
                ->hasConfigFile(RulesetValidation::FILENAME_CONFIG)
                ->hasCommands(
                    RulesetMakeCommand::class,
                    RulesetListCommand::class,
                )->hasInstallCommand(function (InstallCommand $command) {
                $command
                    ->publishConfigFile()
                    ->askToStarRepoOnGitHub(RulesetValidation::REPOSITORY)
                    ->endWith(function (InstallCommand $command) {
                        $repo = RulesetValidation::REPOSITORY;
                        $command->info("Thank you for using {$repo}, have a great day!");
                    });
            });
    }

    public function packageRegistered(): void
    {
        $this->registerBuilder();

        if (RulesetValidation::usesFactoryDecorator()) {
            $this->registerFactoryDecorator();
        } elseif (RulesetValidation::usesFactoryConcrete()) {
            $this->registerFactoryConcrete();
        }
    }

    protected function registerFactoryConcrete(): void
    {
        $this->app->extend('validator', function (BaseFactoryContract $factory, $app) {
            return $this->buildRulesetFactory($app);
        });

        $this->app->alias('validator', RulesetFactoryContract::class);
        $this->app->alias('validator', RulesetFactory::class);
    }

    protected function buildRulesetFactory(Application $app): RulesetFactoryContract
    {
        $factory = new RulesetFactory($app['translator'], $app);

        if ($app->bound('validation.presence')) {
            $factory->setPresenceVerifier($app['validation.presence']);
        }

        return $factory;
    }

    protected function registerFactoryDecorator(): void
    {
        $this->app->extend('validator', function (
            BaseFactoryContract $factory,
            Application $app
        ) {
            return new FactoryDecorator($factory);
        });

        $this->app->alias('validator', RulesetFactoryContract::class);
        $this->app->alias('validator', FactoryDecorator::class);
    }

    protected function registerBuilder(): void
    {
        $this->app->bind(
            'ruleset.builder',
            fn($app, $params) => RuleBuilder::new($params['rules'] ?? [])
        );

        $this->app->alias('ruleset.builder', RuleBuilderContract::class);
        $this->app->alias('ruleset.builder', RuleBuilder::class);
    }
}
