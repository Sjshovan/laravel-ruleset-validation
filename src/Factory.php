<?php

namespace Sjshovan\RulesetValidation;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Factory as BaseValidationFactory;
use Sjshovan\RulesetValidation\Contracts\Ruleset;
use Sjshovan\RulesetValidation\Contracts\Factory as RulesetFactoryContract;

/**
 * Concrete implementation of the RulesetFactoryContract that extends
 * Laravel’s base validation factory to support ruleset-driven validation.
 *
 * @see \Sjshovan\RulesetValidation\Contracts\Factory
 * @see \Illuminate\Validation\Factory
 */
class Factory extends BaseValidationFactory implements RulesetFactoryContract
{
    public function makeFromRuleset(Ruleset $ruleset, array $data = []): Validator
    {
        return $this->make(
            $data,
            $ruleset->rules(),
            $ruleset->messages(),
            $ruleset->attributes()
        );
    }
}