<?php

namespace Sjshovan\RulesetValidation\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;
use Spatie\StructureDiscoverer\Discover;
use Spatie\StructureDiscoverer\Data\DiscoveredStructure;
use Spatie\StructureDiscoverer\Support\Conditions\ConditionBuilder;
use Sjshovan\RulesetValidation\Support\Package;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Path;

#[AsCommand(
    name: 'make:ruleset',
    description: 'Create a new ruleset class, optionally linked to a model.'
)]
class RulesetMakeCommand extends GeneratorCommand
{
    protected $aliases = [
        'ruleset:make',
    ];
    // @formatter:off

    //--= args & options =--

    protected const ARG_NAME = 'ruleset';
    protected const OPT_MODEL = 'model';
    protected const OPT_GEN = 'gen';
    protected const OPT_DRY_RUN = 'dry-run';

    //--= stub-vars =--

    protected const VAR_MODEL = '{{ MODEL }}';
    protected const VAR_CLASS = '{{ CLASS }}';
    protected const VAR_ABSTRACT = '{{ ABSTRACT }}';
    protected const VAR_NAMESPACE = '{{ NAMESPACE }}';
    protected const VAR_USE_STATEMENTS = '{{ USE_STATEMENTS }}';
    protected const VAR_DECLARATION = '{{ DECLARATION }}';

    //--= input =--

    protected string $inputArgName = ''; // sanitized relative path/for/ruleset
    protected ?string $inputOptModel = null; // sanitized full or relative path/to/model

    //--= ruleset =--

    protected string $rulesetName = ''; // class_basename of $inputArgName
    protected string $rulesetFqcn = ''; // App\Path\To\{RulesetName}
    protected string $rulesetPath = ''; // var/www/html/app/path/to/{RulesetName}

    //--= model =--

    protected ?string $modelName = null; // class_basename of $inputOptModel
    protected ?string $modelFqcn = null; // App\Path\To\{ModelName}

    //--= abstract =--

    protected string $classAbstractBase = ''; // configured baseAbstract::class

    protected string $classNameAbstractBase = ''; // class_basename of $classAbstractBase

    protected string $classNameAbstractIntermediate = ''; // (RulesetAbstract or ModelRulesetAbstract)
    protected string $expectedAbstractIntermediateFqcn = ''; // App\Path\To\{AbstractName}

    //--= stub =--

    protected string $stub = ''; // (model-ruleset.stub or ruleset.stub)

    // @formatter:on

    public function handle(): bool|null
    {
        try {
            // set up variables based on input context
            $this->initializeContextVariables();

            // set the type generated, used by parent::handle()
            $this->type = $this->rulesetName;

            // handle dry-run if present...
            if ($this->shouldDryRun()) {
                // get the FQCN of the ruleset
                $fqcn = $this->qualifyClass($this->getNameInput());

                // call the parent's buildClass() method directly to avoid parent::handle()
                $renderedStub = $this->buildClass($fqcn);

                $this->components->info("[dry-run] Would write ruleset to: {$this->getRulesetPath(true)}");

                $this->outputStubPreview($renderedStub);
            } else {
                return parent::handle();
            }
        } catch (\Throwable $e) {
            // if we throw anything, cleanly exit with error messages
            // override default error verbosity so -q (quiet) works as documented
            $this->components->error($e->getMessage(), OutputInterface::VERBOSITY_QUIET);

            $this->line($e->getTraceAsString(), null, OutputInterface::VERBOSITY_VERBOSE);

            return false;
        }

        return null;
    }

    /**
     * Initializes all context-specific variables from CLI inputs.
     */
    protected function initializeContextVariables(): void
    {
        $this->rulesetName = class_basename($this->getNameInput());

        $this->rulesetFqcn = $this->qualifyClass($this->getNameInput());

        $this->rulesetPath = $this->getRulesetPath();

        // temp var to avoid repeating method calls,
        // this allows for better grouping of context-aware concerns
        $hasModelInput = (bool) $this->getModelInput();

        $this->classAbstractBase = $hasModelInput
            ? Package::getBaseModelRulesetAbstractClass()
            : Package::getBaseRulesetAbstractClass();

        $this->classNameAbstractIntermediate = $hasModelInput
            ? Package::CLASSNAME_MODEL_RULESET_ABSTRACT
            : Package::CLASSNAME_RULESET_ABSTRACT;

        $this->setStub($hasModelInput
            ? Package::STUB_MODEL_RULESET
            : Package::STUB_RULESET);

        if ($hasModelInput) {
            $this->modelName = class_basename($this->getModelInput());
            $this->modelFqcn = $this->getModelFqcn();
        }

        $this->classNameAbstractBase = class_basename($this->classAbstractBase);

        // we use the existing ruleset FQCN and expect the abstract to be in the same directory
        $this->expectedAbstractIntermediateFqcn = Str::of($this->rulesetFqcn)
                                                     ->beforeLast('\\')
                                                     ->append('\\'.$this->classNameAbstractIntermediate);
    }

    /**
     * Renders the class stub with variables replaced.
     *
     * If intermediate abstract generation is enabled and needed, it will be created.
     *
     *  Called during dry-run or by parent::handle().
     */
    protected function buildClass($name): string
    {
        // set up default stub replacement variables
        $stubReplacements = [
            self::VAR_NAMESPACE => $this->getNamespace($this->rulesetFqcn),
            self::VAR_CLASS => $this->rulesetName,
            self::VAR_ABSTRACT => $this->classNameAbstractBase,
            self::VAR_MODEL => $this->modelName,
            self::VAR_USE_STATEMENTS => $this->getUseStatements((bool) $this->getModelInput(), true),
            self::VAR_DECLARATION => 'class',
        ];

        // if configured to use intermediate abstracts...
        if (Package::usesIntermediateAbstracts()) {
            $abstractFqcn = $this->getIntermediateAbstractFqcn();

            // if we don't find an existing intermediate abstract, and we want to generate one...
            if (empty($abstractFqcn) && $this->shouldGenerateIntermediateAbstract()) {
                // set up intermediate abstract stub replacement variables
                $intermediateReplacements = array_merge($stubReplacements, [
                    self::VAR_CLASS => $this->classNameAbstractIntermediate,
                    self::VAR_ABSTRACT => $this->classNameAbstractBase,
                    self::VAR_USE_STATEMENTS => $this->getUseStatements((bool) $this->getModelInput(), true),
                    self::VAR_DECLARATION => 'abstract class',
                ]);

                $this->generateIntermediateAbstract($intermediateReplacements);

                // after generating the intermediate abstract, prepare the parent stub generation to extend it
                $this->prepareParentStubForIntermediate($stubReplacements);
            } elseif ($abstractFqcn) {
                // existing intermediate is found, prepare the parent stub generation to extend it
                $this->prepareParentStubForIntermediate($stubReplacements);
            }
        }

        //handled by parent::handle();
        return Package::renderStub($this->getStub(), $stubReplacements);
    }

    /**
     * Generates the intermediate abstract class file.
     *
     * Supports dry-run mode for preview without writing.
     */
    protected function generateIntermediateAbstract(array $replacements): void
    {
        // modify the replacements to class the intermediate abstract
        $replacements[self::VAR_CLASS] = $this->classNameAbstractIntermediate;

        $intermediatePath = Path::join($this->rulesetPath, $this->classNameAbstractIntermediate.'.php');

        $renderedStub = Package::renderStub($this->getStub(), $replacements);

        // handle the dry-run if present...
        if ($this->shouldDryRun()) {
            $this->components->info("[dry-run] Would write abstract ruleset to: {$intermediatePath}");

            $this->outputStubPreview($renderedStub);

            return;
        }

        $this->files->put($intermediatePath, $this->sortImports($renderedStub));

        $this->components->info("{$this->classNameAbstractIntermediate} created at [{$this->rulesetPath}]");
    }

    /**
     * Modifies the stub context to extend an intermediate abstract.
     *
     * Called when an intermediate abstract exists or is generated.
     */
    protected function prepareParentStubForIntermediate(array &$stubReplacements): void
    {
        // set the stub
        $this->setStub(Package::STUB_RULESET);

        // update the abstract and use statements
        $stubReplacements[self::VAR_ABSTRACT] = $this->classNameAbstractIntermediate;
        $stubReplacements[self::VAR_USE_STATEMENTS] = $this->getUseStatements(false, false);
    }

    /**
     * Retrieves and normalizes the ruleset name from input.
     *
     * Appends the suffix if enabled and not already present.
     */
    protected function getNameInput(): string
    {
        if (! blank($this->inputArgName)) {
            return $this->inputArgName;
        }

        $sanitized = $this->sanitizeInput($this->argument(self::ARG_NAME));

        $name = Package::usesRulesetSuffix() && Str::doesntContain($sanitized, Package::getRulesetSuffix())
            ? $sanitized.Package::getRulesetSuffix()
            : $sanitized;

        $this->inputArgName = $this->normalizeClassName($name);

        return $this->inputArgName;
    }

    /**
     * Retrieves and normalizes the model path from input.
     */
    protected function getModelInput(): ?string
    {
        // cached
        if (! is_null($this->inputOptModel)) {
            return $this->inputOptModel;
        }

        // was -m/--model provided at all?
        $flagProvided = $this->isOptionFlagProvided(self::OPT_MODEL);

        if (! $flagProvided) {
            return null; // no model context
        }

        // value if given; when flag present without value, $raw will be null
        $raw = $this->option(self::OPT_MODEL);

        // treat bare -m/--model as "use the base Eloquent model"
        $candidate = blank($raw)
            ? Model::class
            : $raw;

        $sanitized = $this->sanitizeInput($candidate);

        if (blank($sanitized)) {
            return null;
        }

        return $this->inputOptModel = $this->normalizeClassName($sanitized);
    }

    /**
     * Sanitizes a CLI input string into a forward-slash separated path.
     */
    protected function sanitizeInput(string $input): string
    {
        // convert all backslashes to forward-slashes
        $input = str_replace('\\', '/', trim($input));

        // remove all characters except numbers, letters, and forward-slashes
        $input = preg_replace('/[^a-zA-Z0-9_\/]/', '', $input);

        // remove all duplicate forward-slashes
        return preg_replace('#/+#', '/', $input);
    }

    /**
     * Resolves a fully or partially qualified name of the given input.
     *
     * Example: "/path/to/Model/" → "path\to\Model"
     */
    protected function qualifyInput(string $input): string
    {
        return str_replace('/', '\\',
            trim($input, '\\/'));
    }

    /**
     * Converts a sanitized class path to a forward-slashed StudlyCase format.
     *
     * Example: "admin/user_model" → "Admin/UserModel"
     */
    protected function normalizeClassName(string $className): string
    {
        // create segments from forward-slashes
        $segments = explode('/', trim($className, '/'));

        // studly-case all segments
        $segments = array_map([Str::class, 'studly'], $segments);

        // re-add the forward-slashes
        return implode('/', $segments);
    }

    /**
     * Determines the default namespace for the generated ruleset class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        // diff the configured output path from the application path
        $relative = Str::after(Package::getRulesetOutputPath(), app_path());

        // prepend the root namespace to the relative path
        return $rootNamespace.str_replace('/', '\\', $relative);
    }

    /**
     * Determines whether an intermediate abstract should be generated.
     *
     * Checks CLI option or prompts interactively if not set.
     */
    protected function shouldGenerateIntermediateAbstract(): bool
    {
        // was the flag provided at all?
        $flagProvided = $this->isOptionFlagProvided(self::OPT_GEN);

        // if flag NOT provided -> go to prompt
        if (! $flagProvided) {
            return $this->confirmGenerate();
        }

        // raw option value (string|null)
        $raw = $this->option(self::OPT_GEN);

        // flag provided without value -> treat as "yes"
        if (blank($raw)) {
            return true;
        }

        // normalize provided value
        $value = Str::lower((string) $raw);

        // explicit truthy / falsy
        if (in_array($value, ['yes', 'y', 'true', '1'], true)) {
            return true;
        }

        if (in_array($value, ['no', 'n', 'false', '0'], true)) {
            return false;
        }

        // "prompt" (or anything else unexpected) -> ask
        return $this->confirmGenerate();
    }

    /**
     * Determine if the given option flag was present or not
     */
    protected function isOptionFlagProvided(string $option): bool
    {
        return $this->input->hasParameterOption([
            '--'.$option, '-'.substr($option, 0, 1),
        ]);
    }

    /**
     * Determine if the given FQCN is the base eloquent model
     */
    protected function isEloquentModel(string $fqcn): bool
    {
        return $fqcn == Model::class;
    }

    /**
     * Prompt the user to confirm generation of intermediate abstract
     */
    protected function confirmGenerate(): bool
    {
        return $this->components->confirm(
            "'{$this->classNameAbstractIntermediate}' class not found. Generate and extend it?",
            true
        );
    }

    /**
     * Determines whether dry-run mode is enabled.
     */
    protected function shouldDryRun(): bool
    {
        return (bool) $this->option(self::OPT_DRY_RUN);
    }

    /**
     * Resolves the absolute path for the ruleset file or its directory.
     */
    protected function getRulesetPath(bool $includeFile = false): string
    {
        // convert our sanitized name input to an FQCN
        $fqcn = $this->qualifyClass($this->getNameInput());

        // get our path to the ruleset from our FQCN
        $path = $this->getPath($fqcn);

        return $includeFile ? $path : dirname($path);
    }

    /**
     * Resolves the fully qualified class name of the model.
     *
     * Attempts direct class check first, falls back to search and matching.
     *
     * @throws \RuntimeException If no model match is found.
     */
    protected function getModelFqcn(): string
    {
        // qualify our input for the next check
        $qualifiedInput = $this->qualifyInput($this->getModelInput());

        // if input is Model::class it means no input was added to the '-m' option;
        // in this case we want to make a model-ruleset still, so use it.
        if ($this->isEloquentModel($qualifiedInput)) {
            // hand back the eloquent model
            return $qualifiedInput;
        }

        // convert our sanitized name input to an FQCN
        $fqcn = $this->qualifyModel($this->getModelInput());

        // if the FQCN is valid, return it
        if (class_exists($fqcn) && is_subclass_of($fqcn, Model::class)) {
            return $fqcn;
        }

        // ok, let's try a PQCN (partially qualified class name) and search for models
        $pqcn = $this->qualifyInput($this->getModelInput());

        // search for the missing pieces to convert the PQCN into a FQCN, the FQCN must end with the PQCN to qualify
        $models = collect($this->discoverSubclasses(Model::class, ...Package::getModelDiscoveryPaths())->get());

        $match = $models->first(fn(string $fqcn) => Str::endsWith($fqcn, $pqcn));

        if (! $match) {
            // bubble to up handle() which should exit cleanly by returning bool|null
            throw new \RuntimeException("No model found with the name '{$this->getModelInput()}'.");
        }

        return $match;
    }

    /**
     * Finds an intermediate abstract class that matches the expected FQCN.
     *
     * Searches the configured ruleset path for subclasses of the configured base abstract.
     */
    protected function getIntermediateAbstractFqcn(): ?string
    {
        // find classes that extend our base abstract in the ruleset path
        $abstracts = collect($this->discoverSubclasses(
            $this->classAbstractBase,
            $this->rulesetPath
        )->get());

        // return the first that matches our expected intermediate FQCN
        return $abstracts->first(fn(string $fqcn) => Str::is($this->expectedAbstractIntermediateFqcn, $fqcn));
    }

    /**
     * Uses Spatie StructureDiscoverer to locate subclasses of a given base class.
     * Searches given directories.
     *
     * @see https://github.com/spatie/php-structure-discoverer
     */
    public static function discoverSubclasses(string $fqcn, string ...$directories): Discover
    {
        // first try to find classes that extend our given class name directly otherwise,
        // see if it's a child class
        return Discover::in(...$directories)->any(
            ConditionBuilder::create()->classes()->extending($fqcn),
            ConditionBuilder::create()->classes()->custom(
                fn(DiscoveredStructure $structure) => is_subclass_of($structure->getFcqn(), $fqcn)
            )
        );
    }

    protected function buildUseStatement(string $statement): string
    {
        $statement = trim($statement);

        return "use $statement;";
    }

    protected function getUseStatements(bool $useModel, bool $useAbstract): string
    {
        $statements = [];

        if ($useAbstract) {
            $statements[] = $this->buildUseStatement($this->classAbstractBase);
        }

        if ($useModel) {
            $statements[] = $this->buildUseStatement($this->modelFqcn);
        }

        // format if we have statements, otherwise do nothing
        return count($statements)
            ? PHP_EOL.implode(PHP_EOL, array_unique($statements)).PHP_EOL
            : '';
    }

    /**
     * Displays a visual preview of the rendered stub to the console.
     *
     * Used in dry-run mode.
     */
    protected function outputStubPreview(string $content): void
    {
        $this->newLine();
        $this->line(str_repeat('-', 77));
        // preserve original spacing
        $this->output->writeln($content);
        $this->line(str_repeat('-', 77));
        $this->newLine();
    }

    protected function setStub(string $stub)
    {
        $this->stub = $stub;
    }

    /**
     * The stub returned may change depending on input and context
     *
     * @see setStub() usage
     */
    protected function getStub(): string
    {
        return $this->stub;
    }

    protected function getArguments(): array
    {
        return [
            new InputArgument(self::ARG_NAME,
                InputArgument::REQUIRED,
                'The name or path of the ruleset'),
        ];
    }

    protected function getOptions(): array
    {
        return [
            new InputOption(self::OPT_MODEL, 'm',
                InputOption::VALUE_OPTIONAL,
                'The model class name or path'),

            new InputOption(self::OPT_GEN, 'g',
                InputOption::VALUE_OPTIONAL,
                '(yes/no) to generate the intermediate abstract class and skip the prompt'),

            new InputOption(self::OPT_DRY_RUN, 'd',
                InputOption::VALUE_NONE,
                'Output to console instead of generating files'),
        ];
    }
}
