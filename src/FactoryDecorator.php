<?php

namespace Sjshovan\RulesetValidation;

use Sjshovan\RulesetValidation\Contracts\Ruleset;
use Sjshovan\RulesetValidation\Contracts\Factory as RulesetFactoryContract;
use Illuminate\Contracts\Validation\Factory as BaseFactoryContract;
use Illuminate\Contracts\Validation\Validator;

/**
 * Decorator for Laravel's validation factory to support Ruleset-based validation.
 *
 * This class wraps Laravel’s default validation factory and adds support for
 * instantiating a validator directly from a Ruleset instance, while passing
 * through all other calls to the underlying factory.
 *
 * The `makeFromRuleset()` method extracts rules, messages, and attributes
 * from the given Ruleset and builds a Validator instance using the decorated
 * factory. All other methods proxy directly to the inner factory.
 *
 * This decorator is only enabled when configured via the package's
 * `factory_decorator` behavior flag.
 *
 * @see \Sjshovan\RulesetValidation\Contracts\Factory
 * @see \Illuminate\Contracts\Validation\Factory
 */
class FactoryDecorator implements RulesetFactoryContract
{
    public function __construct(readonly BaseFactoryContract $innerFactory) {}

    public function makeFromRuleset(Ruleset $ruleset, array $data = []): Validator
    {
        return $this->innerFactory->make(
            $data,
            $ruleset->rules(),
            $ruleset->messages(),
            $ruleset->attributes()
        );
    }

    public function make(array $data, array $rules, array $messages = [], array $attributes = []): Validator
    {
        return $this->innerFactory->make($data, $rules, $messages, $attributes);
    }

    public function extend($rule, $extension, $message = null): void
    {
        $this->innerFactory->extend($rule, $extension, $message);
    }

    public function extendImplicit($rule, $extension, $message = null): void
    {
        $this->innerFactory->extendImplicit($rule, $extension, $message);
    }

    public function replacer($rule, $replacer): void
    {
        $this->innerFactory->replacer($rule, $replacer);
    }

    public function __call(string $method, array $arguments)
    {
        return $this->innerFactory->$method(...$arguments);
    }
}