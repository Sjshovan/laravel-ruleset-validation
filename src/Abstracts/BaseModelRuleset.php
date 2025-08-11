<?php

namespace Sjshovan\RulesetValidation\Abstracts;

use Illuminate\Database\Eloquent\Model;
use Sjshovan\RulesetValidation\Contracts\ModelRuleset;
use Sjshovan\RulesetValidation\Traits\HasRuleBuilder;
use Sjshovan\RulesetValidation\Traits\HasRulesetValidator;
use Sjshovan\RulesetValidation\Traits\InstantiatesModel;
use Sjshovan\RulesetValidation\Traits\IsRuleset;

abstract class BaseModelRuleset implements ModelRuleset
{
    use IsRuleset;
    use InstantiatesModel;
    use HasRuleBuilder;
    use HasRulesetValidator;

    final public function __construct(?Model $model = null)
    {
        $this->instantiateModel($model);
    }

    final public static function for(Model $model): static
    {
        return new static($model);
    }

    abstract protected static function modelClass(): string;

    abstract public function rules(): array;
}