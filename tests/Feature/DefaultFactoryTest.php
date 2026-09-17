<?php

namespace Sjshovan\RulesetValidation\Tests\Feature;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Validation\Factory as LaravelFactory;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Sjshovan\RulesetValidation\Abstracts\BaseRuleset;
use Sjshovan\RulesetValidation\Tests\TestCase;

class DefaultFactoryTest extends TestCase
{
    protected string $factoryMode = '';

    public function test_default_configuration_keeps_laravels_factory(): void
    {
        $this->assertSame(LaravelFactory::class, get_class(app(Factory::class)));
    }

    public function test_rulesets_preserve_native_rules_and_return_only_validated_fields(): void
    {
        $ruleset = new class extends BaseRuleset {
            public function rules(): array
            {
                return [
                    'kind' => ['required', Rule::in(['regular', 'foil'])],
                    'items' => ['required', 'array'],
                    'items.*.name' => ['required', 'string', 'regex:/^(foo|bar)$/'],
                    'reference' => [Rule::requiredIf(true), function ($attribute, $value, $fail) {
                        if ($value !== 'approved') {
                            $fail('The reference is invalid.');
                        }
                    }],
                ];
            }
        };

        $input = ['kind' => 'foil', 'items' => [['name' => 'bar']], 'reference' => 'approved'];
        $this->assertEquals($input, $ruleset->validator($input + ['admin' => true])->validate());

        $invalid = $ruleset->validator(['kind' => 'unknown', 'items' => [['name' => 'bad']], 'reference' => 'rejected']);
        $this->assertTrue($invalid->fails());
        $this->assertEqualsCanonicalizing(['kind', 'reference', 'items.0.name'], array_keys($invalid->errors()->messages()));
        $this->assertTrue($ruleset->validator(['kind' => 'foil', 'items' => [['name' => 'foo']]])->errors()->has('reference'));
    }

    public function test_invalid_input_throws_laravels_validation_exception(): void
    {
        $ruleset = new class extends BaseRuleset {
            public function rules(): array
            {
                return ['name' => ['required', 'string']];
            }
        };

        $this->expectException(ValidationException::class);
        $ruleset->validator([])->validate();
    }
}
