<?php

namespace Sjshovan\RulesetValidation\Contracts;

use \Illuminate\Contracts\Validation\Factory as BaseFactoryContract;
use Illuminate\Contracts\Validation\Validator;

/**
 * Extended validation factory contract for ruleset integration.
 *
 * Adds support for instantiating a Validator directly from a Ruleset instance,
 * allowing package users to validate structured ruleset objects using Laravel's
 * validation engine.
 */
interface Factory extends BaseFactoryContract
{
    /**
     * Create a Validator instance using the given Ruleset and input data.
     */
    public function makeFromRuleset(Ruleset $ruleset, array $data = []): Validator;
}