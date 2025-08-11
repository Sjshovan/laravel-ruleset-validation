<?php

namespace Sjshovan\RulesetValidation\Commands;

use Illuminate\Console\Command;
use Sjshovan\RulesetValidation\Contracts\ModelRuleset as ModelRulesetContract;
use Sjshovan\RulesetValidation\Support\Package;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;
use Spatie\StructureDiscoverer\Discover;
use Spatie\StructureDiscoverer\Support\Conditions\ConditionBuilder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'ruleset:list',
    description: 'List all ruleset classes with optional filtering and colorized output.'
)]
class RulesetListCommand extends Command
{
    // @formatter:off

    //--= opts =--

    protected const OPT_ABSTRACT = 'abstract'; // -a
    protected const OPT_CONCRETE = 'concrete'; // -c
    protected const OPT_STANDARD = 'standard'; // -s
    protected const OPT_MODEL    = 'model';    // -m

    //--= colors =--

    protected const COLOR_ABSTRACT  = 'blue';
    protected const COLOR_CONCRETE  = 'default';
    protected const COLOR_WARNING   = 'red';
    protected const COLOR_NAMESPACE = 'gray';
    protected const COLOR_STANDARD  = 'green';
    protected const COLOR_MODEL     = 'magenta';

    // @formatter:on

    /**
     * Entry point for the Artisan command.
     */
    public function handle(): int
    {
        $filterAbstract = (bool) $this->option(self::OPT_ABSTRACT);
        $filterConcrete = (bool) $this->option(self::OPT_CONCRETE);
        $filterStandard = (bool) $this->option(self::OPT_STANDARD);
        $filterModel = (bool) $this->option(self::OPT_MODEL);

        $onlyAbstract = $filterAbstract && ! $filterConcrete;
        $onlyConcrete = $filterConcrete && ! $filterAbstract;

        $onlyStandard = $filterStandard && ! $filterModel;
        $onlyModel = $filterModel && ! $filterStandard;

        $this->displayFilterInfo($filterAbstract, $filterConcrete, $filterStandard, $filterModel);

        $structures = collect(
            $this->discoverRulesets(
                [Package::getRulesetOutputPath()],
                $onlyConcrete,
                $onlyAbstract,
                $onlyStandard,
                $onlyModel
            )->full()->get()
        );

        if ($structures->isEmpty()) {
            $this->components->warn('No rulesets matched your filters.');

            return self::SUCCESS;
        }

        $this->info("Found: {$structures->count()}");;

        $rows = $structures
            ->sortBy('name')
            ->map(function (DiscoveredStructure $structure) {
                $isModel = $this->isModelRuleset($structure->getFcqn());

                return [
                    $structure->name,
                    $this->colorizeClassType($structure->isAbstract),
                    $this->colorizeRulesetType($isModel),
                    $this->colorizeExtends($structure->extends),
                    "<fg=".self::COLOR_NAMESPACE.">{$structure->namespace}</>",
                ];
            });

        $this->table(['Basename', 'Class Type', 'Ruleset Type', 'Extends', 'Namespace'], $rows);

        return self::SUCCESS;
    }

    /**
     * Display filter-type used for discovery.
     */
    protected function displayFilterInfo(
        bool $filterAbstract,
        bool $filterConcrete,
        bool $filterStandard,
        bool $filterModel
    ): void {
        $all = $filterAbstract && $filterConcrete && $filterStandard && $filterModel;
        $filters = collect([
            ! $all && $filterAbstract ? 'Abstract' : null,
            ! $all && $filterConcrete ? 'Concrete' : null,
            ! $all && $filterStandard ? 'Standard' : null,
            ! $all && $filterModel ? 'Model' : null,
        ])->filter();

        $label = $filters->isEmpty() ? 'All Rulesets' : $filters->implode(', ');
        $this->components->info("Filters: {$label}");
    }

    /**
     * Returns a colorized label for the class type (abstract/concrete).
     */
    protected function colorizeClassType(bool $isAbstract): string
    {
        return $isAbstract
            ? "<fg=".self::COLOR_ABSTRACT.">Abstract</>"
            : "<fg=".self::COLOR_CONCRETE.">Concrete</>";
    }

    /**
     * Returns a colorized label for the ruleset type (standard/model).
     */
    protected function colorizeRulesetType(bool $isModel): string
    {
        return $isModel
            ? "<fg=".self::COLOR_MODEL.">Model</>"
            : "<fg=".self::COLOR_STANDARD.">Standard</>";
    }

    /**
     * Returns a colorized label for the class being extended.
     */
    protected function colorizeExtends(?string $fqcn): string
    {
        $basename = class_basename($fqcn ?? 'None');

        if (! $fqcn || ! class_exists($fqcn)) {
            return "<fg=".self::COLOR_WARNING.">{$basename}</>";
        }

        return $this->isAbstractClass($fqcn)
            ? "<fg=".self::COLOR_ABSTRACT.">{$basename}</>"
            : "<fg=".self::COLOR_CONCRETE.">{$basename}</>";
    }

    /**
     * Uses Spatie StructureDiscoverer to locate rulesets with optional filtering.
     *
     * @see https://github.com/spatie/php-structure-discoverer
     */
    protected function discoverRulesets(
        array $directories,
        bool $onlyRegular = false,   // concrete-only
        bool $onlyAbstract = false,  // abstract-only
        bool $onlyStandard = false,  // standard-only
        bool $onlyModel = false      // model-only
    ): Discover
    {
        return Discover::in(...$directories)->any(
            ConditionBuilder::create()->classes()->custom(
                function (DiscoveredStructure $structure) use (
                    $onlyRegular,
                    $onlyAbstract,
                    $onlyStandard,
                    $onlyModel
                ): bool {
                    // abstract/concrete filter
                    if ($onlyRegular && $structure->isAbstract) {
                        return false;
                    }
                    if ($onlyAbstract && ! $structure->isAbstract) {
                        return false;
                    }

                    if ($onlyStandard || $onlyModel) {
                        $isModel = $this->isModelRuleset($structure->getFcqn());

                        if ($onlyStandard && $isModel) {
                            return false;
                        }
                        if ($onlyModel && ! $isModel) {
                            return false;
                        }
                    }

                    return true;
                }
            )
        );
    }

    /**
     * Determine if the given FQCN is an abstract class.
     * Memoized for performance.
     */
    protected function isAbstractClass(string $fqcn): bool
    {
        static $cache = [];

        if (! class_exists($fqcn)) {
            return false;
        }

        return $cache[$fqcn] ??= (new \ReflectionClass($fqcn))->isAbstract();
    }

    /**
     * Determine if the given FQCN implements the ModelRuleset contract.
     * Memoized for performance.
     */
    protected function isModelRuleset(string $fqcn): bool
    {
        static $cache = [];

        if (! class_exists($fqcn)) {
            return false;
        }

        return $cache[$fqcn] ??= (new \ReflectionClass($fqcn))->implementsInterface(ModelRulesetContract::class);
    }

    /**
     * Define the command options.
     */
    protected function getOptions(): array
    {
        return [
            new InputOption(self::OPT_ABSTRACT, 'a', InputOption::VALUE_NONE, 'Include only abstract rulesets'),
            new InputOption(self::OPT_CONCRETE, 'c', InputOption::VALUE_NONE, 'Include only concrete rulesets'),

            new InputOption(self::OPT_STANDARD, 's', InputOption::VALUE_NONE, 'Include only standard rulesets'),
            new InputOption(self::OPT_MODEL, 'm', InputOption::VALUE_NONE, 'Include only model rulesets'),
        ];
    }
}
