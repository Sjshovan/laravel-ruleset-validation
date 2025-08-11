<?php

namespace Sjshovan\RulesetValidation\Contracts;

use Illuminate\Support\Collection;

/**
 * Interface for building validation rules in a fluent and composable way.
 */
interface RuleBuilder
{
    /**
     * Clears all defined rules.
     */
    public function clear(): static;

    /**
     * Sets rules for a given key, replacing any existing ones.
     */
    public function set(string $key, string|array ...$rules): static;

    /**
     * Adds rules to an existing key.
     */
    public function add(string $key, string|array ...$rules): static;

    /**
     * Merges an array of rules with the existing rules.
     */
    public function merge(array $rules): static;

    /**
     * Removes one or more rule keys.
     */
    public function remove(string|array ...$keys): static;

    /**
     * Gets all rules as an array.
     */
    public function get(): array;

    /**
     * Gets the rules as a Laravel Collection.
     */
    public function collect(): Collection;

    /**
     * Prepends a rule to the given key.
     */
    public function prepend(string $key, string $attribute): static;

    /**
     * Prepends a rule to all keys, except those excluded.
     */
    public function prependAll(string $attribute, string|array ...$except): static;

    /**
     * Conditionally apply a callback to the builder.
     */
    public function when(bool $condition, callable $callback): static;
}

