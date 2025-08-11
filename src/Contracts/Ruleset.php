<?php

namespace Sjshovan\RulesetValidation\Contracts;

interface Ruleset
{
    /**
     * Return the array of validation rules.
     */
    public function rules(): array;

    /**
     * Return custom error messages for the ruleset.
     */
    public function messages(): array;

    /**
     * Return custom attribute names for the ruleset.
     */
    public function attributes(): array;
}