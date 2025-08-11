<?php

namespace Sjshovan\RulesetValidation\Abstracts;

use Sjshovan\RulesetValidation\Contracts\Ruleset;
use Sjshovan\RulesetValidation\Traits\HasRuleBuilder;
use Sjshovan\RulesetValidation\Traits\HasRulesetValidator;
use Sjshovan\RulesetValidation\Traits\IsRuleset;

abstract class BaseRuleset implements Ruleset
{
    use IsRuleset;
    use HasRuleBuilder;
    use HasRulesetValidator;

    final public function __construct() {}

    final public static function new(): static
    {
        return new static();
    }

    abstract public function rules(): array;
}