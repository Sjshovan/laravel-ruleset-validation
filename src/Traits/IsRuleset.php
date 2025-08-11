<?php

namespace Sjshovan\RulesetValidation\Traits;

/**
 *
 * Provides a default implementation of the Ruleset interface with
 * empty rules, messages, and attributes.
 *
 * @see \Sjshovan\RulesetValidation\Contracts\Ruleset
 */
trait IsRuleset
{
    public function rules(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [];
    }

    public function attributes(): array
    {
        return [];
    }
}