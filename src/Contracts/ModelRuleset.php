<?php

namespace Sjshovan\RulesetValidation\Contracts;

use Illuminate\Database\Eloquent\Model;

interface ModelRuleset extends Ruleset
{
    /**
     * Return an eloquent model.
     */
    public function model(): Model;
}