<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Output Paths
    |--------------------------------------------------------------------------
    |
    | These paths control where generated ruleset classes are stored and
    | where model classes are discovered for model-aware rulesets.
    |
    */

    'paths' => [
        // Directory where generated rulesets will be stored.
        'ruleset_output' => app_path('Rulesets'),

        // Directories to scan when discovering models for model-based rulesets.
        'model_discovery' => [app_path('Models')],
    ],

    /*
    |--------------------------------------------------------------------------
    | Class Inheritance and Naming Conventions
    |--------------------------------------------------------------------------
    |
    | These settings define structural and naming defaults used during ruleset
    | generation — including which base abstract classes to extend and what
    | suffix to apply to generated ruleset classes.
    |
    */

    'convention' => [

        // Default base abstract classes for ruleset generation.
        'base_abstracts' => [
            'ruleset' => \Sjshovan\RulesetValidation\Abstracts\BaseRuleset::class,
            'model_ruleset' => \Sjshovan\RulesetValidation\Abstracts\BaseModelRuleset::class,
        ],

        // Suffix applied to ruleset class names, e.g., "UserRuleset".
        // If the suffix already exists anywhere within the name, it won't modify it
        'ruleset_suffix' => 'Ruleset',
    ],

    /*
    |--------------------------------------------------------------------------
    | Behavioral Flags
    |--------------------------------------------------------------------------
    |
    | These flags control optional package behaviors, including whether to
    | decorate Laravel’s default validator factory, generate intermediate
    | abstract classes, or enforce ruleset suffix conventions during generation.
    |
    */

    'behavior' => [

        // Whether to decorate Laravel's validator factory.
        'factory_decorator' => false,

        // Whether to replace Laravel's validator factory.
        'factory_concrete' => false,

        // Convention-based behavior toggles.
        'convention' => [

            // Whether to use an intermediate abstract when generating a ruleset.
            'intermediate_abstracts' => true,

            // Whether to enforce the configured ruleset suffix during generation.
            'ruleset_suffix' => true,
        ],
    ],
];
