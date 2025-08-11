<?php

namespace Sjshovan\RulesetValidation\Tests\Feature;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Collection;
use Sjshovan\RulesetValidation\Abstracts\BaseRuleset;
use Sjshovan\RulesetValidation\RuleBuilder;
use Sjshovan\RulesetValidation\Tests\TestCase;

class RuleBuilderTest extends TestCase
{
    /** @test */
    public function it_supports_fluent_composition(): void
    {
        $rules = (new class extends BaseRuleset{
            public function rules(): array
            {
                return $this->builder([
                    'email' => 'email|max:64',
                    'profession' => 'string',
                    'age' => 'int|min:12',
                    'weight' => 'int',
                ])
                            ->add('name', 'string', 'min:4|max:32', ['not_in:hello,world'])
                            ->remove('age', 'weight')
                            ->prependAll('required')
                            ->prepend('profession', 'sometimes')
                            ->get();
            }
        })->rules();

        $this->assertArrayHasKey('name', $rules, 'Expected name in rules');
        $this->assertStringContainsString('required', implode('|', $rules['email']),
            'Expected required to be prepended');
        $this->assertArrayNotHasKey('age', $rules, 'Expected age to be removed');
        $this->assertArrayNotHasKey('weight', $rules, 'Expected weight to be removed');
    }

    /** @test */
    public function it_adds_and_prepends_rules_and_normalizes_strings()
    {
        $rules = RuleBuilder::new([
            'email' => 'required|string|email',
        ])
                            ->add('email', 'max:255')
                            ->prepend('email', 'nullable')
                            ->get();

        $this->assertSame(
            ['nullable', 'required', 'string', 'email', 'max:255'],
            $rules['email']
        );
    }

    /** @test */
    public function it_merges_arrays_and_pipe_strings_and_dedupes()
    {
        $rules = RuleBuilder::new([
            'email' => 'required|email',
        ])
                            ->merge([
                                'email' => ['email', 'max:64'], // duplicate 'email' should be deduped
                            ])
                            ->get();

        $this->assertSame(['required', 'email', 'max:64'], $rules['email']);
    }

    /** @test */
    public function it_removes_keys()
    {
        $rules = RuleBuilder::new([
            'a' => 'string',
            'b' => 'integer',
            'c' => 'boolean',
        ])
                            ->remove('b', 'c')
                            ->get();

        $this->assertArrayHasKey('a', $rules);
        $this->assertArrayNotHasKey('b', $rules);
        $this->assertArrayNotHasKey('c', $rules);
    }

    /** @test */
    public function it_ignores_remove_for_missing_keys()
    {
        $rules = RuleBuilder::new([
            'x' => 'int',
        ])
                            ->remove('nope') // should be a no-op
                            ->get();

        $this->assertArrayHasKey('x', $rules);
        $this->assertSame(['int'], $rules['x']);
    }

    /** @test */
    public function it_clears_and_sets_fresh_rules()
    {
        $rules = RuleBuilder::new([
            'email' => 'required|email',
        ])
                            ->clear()
                            ->set('username', 'required')
                            ->get();

        $this->assertArrayNotHasKey('email', $rules);
        $this->assertSame(['required'], $rules['username']);
    }

    /** @test */
    public function it_prepends_all_with_optional_exclusions()
    {
        $rules = RuleBuilder::new([
            'name' => 'string',
            'email' => 'email',
        ])
                            ->prependAll('required', ['email']) // exclude 'email'
                            ->get();

        $this->assertSame(['required', 'string'], $rules['name']);
        $this->assertSame(['email'], $rules['email']); // untouched
    }

    /** @test */
    public function it_applies_when_conditionally_true_and_skips_when_false()
    {
        $on = RuleBuilder::new([])->when(true, function ($b) {
            $b->set('x', 'required');
        })->get();

        $off = RuleBuilder::new([])->when(false, function ($b) {
            $b->set('x', 'required');
        })->get();

        $this->assertArrayHasKey('x', $on);
        $this->assertArrayNotHasKey('x', $off);
    }

    /** @test */
    public function it_collects_rules_as_a_collection()
    {
        $collection = RuleBuilder::new(['title' => 'required|string'])->collect();

        $this->assertInstanceOf(Collection::class, $collection);
        $this->assertSame(['required', 'string'], $collection->get('title'));
    }

    /** @test */
    public function its_overwrites_with_set_while_add_appends()
    {
        $rules = RuleBuilder::new([])
                            ->set('email', 'required|email')
                            ->add('email', 'max:191')
                            ->get();

        $this->assertSame(['required', 'email', 'max:191'], $rules['email']);
    }

    /** @test */
    public function it_adds_new_keys_and_merges_existing_keys()
    {
        $rules = RuleBuilder::new([
            'name' => 'required|string',
        ])
                            ->merge([
                                'name' => 'max:50',
                                'email' => 'nullable|email',
                            ])
                            ->get();

        $this->assertSame(['required', 'string', 'max:50'], $rules['name']);
        $this->assertSame(['nullable', 'email'], $rules['email']);
    }

    /** @test */
    public function it_normalizes_all_mixed_rule_inputs(): void
    {
        $customRule = new class implements Rule{
            public function passes($attribute, $value): bool
            {
                return true;
            }

            public function message(): string
            {
                return 'ok';
            }
        };

        $closureRule = function (string $attribute, $value, $fail) { /* no-op */
        };

        // Your exact cases (input => expected)
        $cases = [
            'A_simple_pipe' => [
                'input' => ['required|string|email'],
                'expected' => ['required', 'string', 'email'],
            ],

            'B_mixed_array_and_pipe' => [
                'input' => [['nullable', 'email'], 'required|string'],
                'expected' => ['nullable', 'email', 'required', 'string'],
            ],

            'C_nested_arrays' => [
                'input' => [[['required'], ['string', 'max:255'], [['min:3']]]],
                'expected' => ['required', 'string', 'max:255', 'min:3'],
            ],

            'D_duplicates' => [
                'input' => ['required|string', ['string', 'required', 'max:10'], 'required|max:10'],
                'expected' => ['required', 'string', 'max:10'],
            ],

            'E_empty_values' => [
                'input' => ['', null, [], 'required||string'],
                'expected' => ['required', 'string'],
            ],

            // Regex kept intact even with internal pipes
            'F_regex_array_safe' => [
                'input' => [['regex:/^foo\|bar$/', 'string', 'min:3']],
                'expected' => ['regex:/^foo\|bar$/', 'string', 'min:3'],
            ],

            // Custom Rule object preserved
            'G_rule_object' => [
                'input' => [$customRule, 'required|string'],
                'expected' => [$customRule, 'required', 'string'],
            ],

            // Closure preserved
            'H_closure_rule' => [
                'input' => [$closureRule, 'required'],
                'expected' => [$closureRule, 'required'],
            ],

            // Numeric-like values preserved (uniqueStrict behavior)
            'I_numeric_like' => [
                'input' => ['in:0,1', '0', 0, 'required'],
                'expected' => ['in:0,1', '0', 0, 'required'],
            ],

            'J_multi_variadic' => [
                'input' => ['required|string', ['email', ['max:255']], 'nullable|email', ['string']],
                'expected' => ['required', 'string', 'email', 'max:255', 'nullable'],
            ],
            // Regex passed as a plain string (guard should keep it intact)
            'F2_regex_string_guard' => [
                'input' => ['regex:/^foo\|bar$/', 'string'],
                'expected' => ['regex:/^foo\|bar$/', 'string'],
            ],

            // Pipe string wrapped in an array (recursion should still split it)
            'K_array_wrapped_pipe' => [
                'input' => [['required|string']],
                'expected' => ['required', 'string'],
            ],

            'L_object_same_instance_deduped' => [
                'input' => [$customRule, $customRule, 'required'],
                'expected' => [$customRule, 'required'],
            ],

            'M_object_same_instances_are_deduped' => (function () {
                $anotherRule = new class implements \Illuminate\Contracts\Validation\Rule{
                    public function passes($attribute, $value): bool
                    {
                        return true;
                    }

                    public function message(): string
                    {
                        return 'ok';
                    }
                };

                return [
                    'input' => [$anotherRule, 'required', $anotherRule],
                    'expected' => [$anotherRule, 'required'],
                ];
            })(),

            'N_closure_same_instance_deduped' => [
                'input' => [$closureRule, $closureRule, 'string'],
                'expected' => [$closureRule, 'string'],
            ],

            // Two different closures → NOT deduped
            'N2_closure_different_instances_not_deduped' => (function () {
                $c1 = function ($a, $v, $f) {};
                $c2 = function ($a, $v, $f) {};

                return [
                    'input' => [$c1, 'string', $c2],
                    'expected' => [$c1, 'string', $c2],
                ];
            })(),
        ];

        foreach ($cases as $label => $case) {
            $builder = RuleBuilder::new()->set('field', $case['input']);
            $actual = $builder->get()['field'];

            $this->assertSame(
                $case['expected'],
                $actual,
                "Failed asserting normalization for case: {$label}"
            );
        }
    }

    /** @test */
    public function it_prepends_flat_and_dedupes_from_tail()
    {
        $rules = RuleBuilder::new(['x' => ['required', 'string']])
                            ->prepend('x', ['required', 'nullable'])
                            ->get();

        $this->assertSame(['required', 'nullable', 'string'], $rules['x']); // no ['required', ...] nesting
    }

    /** @test */
    public function it_keeps_string_and_int_versions_distinct()
    {
        $rules = RuleBuilder::new()->set('f', ['0', 0, 'in:0,1'])->get();
        $this->assertSame(['0', 0, 'in:0,1'], $rules['f']);
    }

    /** @test */
    public function it_yield_identical_results_for_variadic_and_single_array_paths()
    {
        $input = ['required|string', ['email', ['max:255']], 'nullable|email', ['string']];

        $a = RuleBuilder::new()->set('f', $input)->get()['f'];
        $b = RuleBuilder::new()->set('f', ...$input)->get()['f'];

        $this->assertSame($a, $b);
    }
}