<?php

namespace Sjshovan\RulesetValidation\Traits;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Contracts\Validation\Factory as BaseFactoryContract;
use Sjshovan\RulesetValidation\Contracts\Ruleset;

/**
 * @phpstan-require-implements \Sjshovan\RulesetValidation\Contracts\Ruleset
 */
trait HasRulesetValidator
{
    final public function validator(array $data = []): Validator
    {
        /** @var Ruleset $this */
        return app(BaseFactoryContract::class)->make(
            $data,
            $this->rules(),
            $this->messages(),
            $this->attributes(),
        );
    }
}