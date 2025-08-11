<?php

namespace Sjshovan\RulesetValidation\Traits;

use Sjshovan\RulesetValidation\Contracts\RuleBuilder;

trait HasRuleBuilder
{
    final protected function builder(array $rules = []): RuleBuilder
    {
        return app(RuleBuilder::class, compact('rules'));
    }
}