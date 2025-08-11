<?php

declare(strict_types=1);

namespace Sjshovan\RulesetValidation;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Sjshovan\RulesetValidation\Contracts\RuleBuilder as RuleBuilderContract;

/**
 * A fluent utility for building, merging, and manipulating Laravel validation rules.
 *
 * @example
 *  $builder = RuleBuilder::new([
 *      'email' => 'required|string|email',
 *  ])
 *  ->add('email', 'max:255')
 *  ->prepend('email', 'nullable')
 *  ->get();
 *
 *  // ['email' => ['nullable', 'required', 'string', 'email', 'max:255']]
 *
 * @example
 *  $rules = RuleBuilder::new([
 *      'name' => 'required|string',
 *  ])
 *  ->merge([
 *      'name' => ['max:50'],
 *      'email' => 'nullable|email',
 *  ])
 *  ->remove('name')
 *  ->get();
 *
 *  // ['email' => ['nullable', 'email']]
 */
final class RuleBuilder implements RuleBuilderContract
{
    protected Collection $rules;

    public function __construct(array $rules = [])
    {
        // Preserve keys; normalize each value
        $this->rules = collect($rules)->map(fn($v) => $this->normalizeRules($v));
    }

    public static function new(array $rules = []): static
    {
        return new static($rules);
    }

    /**
     * Normalize the given rules into a flat, unique array of strings.
     *
     * @example
     *  normalizeRules('required|string', ['max:255']) => ['required', 'string', 'max:255']
     *  normalizeRules(['nullable', 'email']) => ['nullable', 'email']
     *  normalizeRules() => []
     */
    protected function normalizeRules(string|array ...$rules): array
    {
        $normalize = function ($rule) use (&$normalize): array {
            if (blank($rule)) {
                return [];
            }

            if (is_string($rule)) {
                if (str_starts_with($rule, 'regex:')) {
                    return [$rule];
                }

                $segments = array_map('trim', explode('|', $rule));

                return array_values(array_filter($segments, static fn($segment) => ! blank($segment)));
            }

            if (is_array($rule)) {
                return collect($rule)
                    ->flatMap(fn($nestedRule) => $normalize($nestedRule))
                    ->all();
            }

            return [$rule];
        };

        return collect($rules)
            ->flatMap(fn($rule) => $normalize($rule))
            ->uniqueStrict()
            ->values()
            ->all();
    }

    protected function uniqueMerge(array $left, array $right): array
    {
        return collect($left)
            ->merge(collect($right)->flatten())
            ->unique()
            ->values()
            ->all();
    }

    public function clear(): static
    {
        $this->rules = collect();

        return $this;
    }

    public function set(string $key, string|array ...$rules): static
    {
        $this->rules->put($key, $this->normalizeRules(...$rules));

        return $this;
    }

    public function add(string $key, string|array ...$rules): static
    {
        $existing = (array) $this->rules->get($key, []);

        $normalized = $this->normalizeRules(...$rules);
        
        $this->rules->put(
            $key,
            $this->uniqueMerge($existing, $normalized)
        );

        return $this;
    }

    public function merge(array $rules): static
    {
        foreach ($rules as $key => $value) {
            $existing = (array) $this->rules->get($key, []);

            $new = $this->normalizeRules($value);

            $this->rules->put(
                $key,
                $this->uniqueMerge($existing, $new)
            );
        }

        return $this;
    }

    public function remove(string|array ...$keys): static
    {
        $toRemove = Arr::flatten($keys);

        $this->rules = $this->rules->except($toRemove);

        return $this;
    }

    public function get(): array
    {
        return $this->rules->toArray();
    }

    public function collect(): Collection
    {
        return collect($this->rules->all());
    }

    public function prepend(string $key, string|array ...$attributes): static
    {
        $existing = (array) $this->rules->get($key, []);

        $normalized = $this->normalizeRules(...$attributes);

        $remaining = array_values(array_diff($existing, $normalized));

        $this->rules->put(
            $key,
            $this->uniqueMerge($normalized, $remaining)
        );

        return $this;
    }

    public function prependAll(string $attribute, string|array ...$except): static
    {
        $toExclude = Arr::flatten($except);

        foreach ($this->rules as $key => $_) {
            if (! in_array($key, $toExclude, true)) {
                $this->prepend($key, $attribute);
            }
        }

        return $this;
    }

    public function when(bool $condition, callable $callback): static
    {
        if ($condition) {
            $callback($this);
        }

        return $this;
    }
}
