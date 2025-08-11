<?php

namespace Sjshovan\RulesetValidation\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Sjshovan\RulesetValidation\Abstracts\BaseModelRuleset;
use Sjshovan\RulesetValidation\Abstracts\BaseRuleset;
use Illuminate\Contracts\Validation\Factory as BaseFactoryContract;
use Sjshovan\RulesetValidation\Factory as CustomFactory;
use Sjshovan\RulesetValidation\Contracts\Factory as CustomFactoryContract;
use Sjshovan\RulesetValidation\Tests\TestCase;

class TestPost extends Model
{
    protected $table = 'posts';
    protected $fillable = ['slug'];
}

class RulesetValidationTest extends TestCase
{
    /** @test */
    public function it_instantiates_via_helpers()
    {
        $s = new class extends BaseRuleset{
            public function rules(): array
            {
                return [];
            }
        };

        $m = new class extends BaseModelRuleset{
            public static function modelClass(): string
            {
                return TestPost::class;
            }

            public function rules(): array
            {
                return [];
            }
        };

        $this->assertInstanceOf(get_class($s), $s::new());
        $this->assertInstanceOf(get_class($m), $m::for(new TestPost()));
    }

    /** @test */
    public function it_rejects_wrong_model_type()
    {
        $this->expectException(\LogicException::class);

        (new class(new TestPost()) extends BaseModelRuleset{
            protected static function modelClass(): string
            {
                return \stdClass::class;
            }

            public function rules(): array
            {
                return [];
            }
        });
    }

    /** @test */
    public function it_respects_global_validator_extensions()
    {
        ValidatorFacade::extend('is_foo', fn($attribute, $value) => $value === 'foo');

        $ruleset = new class extends BaseRuleset{
            public function rules(): array
            {
                return ['x' => 'required|is_foo'];
            }
        };

        $this->assertTrue($ruleset->validator(['x' => 'foo'])->passes());
        $this->assertTrue($ruleset->validator(['x' => 'bar'])->fails());
    }

    /** @test */
    public function it_validates_using_ruleset_validator_on_standard_ruleset(): void
    {
        $validator = (new class extends BaseRuleset{
            public function rules(): array
            {
                return $this->builder()
                            ->set('email', 'required|email')
                            ->set('name', 'required|string|min:3')
                            ->get();
            }
        })->validator(['email' => 'foo@example.com', 'name' => 'Sid']);

        $this->assertTrue($validator->passes(), 'Expected standard ruleset to pass validation');
    }

    /** @test */
    public function it_fails_validation_on_missing_required_fields(): void
    {
        $validator = (new class extends BaseRuleset{
            public function rules(): array
            {
                return [
                    'email' => 'required|email',
                    'name' => 'required|string|min:3',
                ];
            }
        })->validator(['email' => 'not-an-email']);

        $this->assertTrue($validator->fails(), 'Expected validation to fail');
        $this->assertArrayHasKey('name', $validator->errors()->toArray(), 'Expected name to have an error');
        $this->assertArrayHasKey('email', $validator->errors()->toArray(), 'Expected email to have an error');
    }

    /** @test */
    public function it_builds_a_validator_via_the_custom_factory(): void
    {
        $ruleset = new class extends BaseRuleset{
            public function rules(): array
            {
                return ['title' => 'required|string|min:2'];
            }
        };

        $validator = app(CustomFactoryContract::class)->makeFromRuleset($ruleset, ['title' => 'OK']);

        $this->assertTrue($validator->passes(), 'Expected factory-built validator to pass');

        $validator = app(CustomFactory::class)->makeFromRuleset($ruleset, ['title' => 'OK']);

        $this->assertTrue($validator->passes(), 'Expected factory-built validator to pass');
    }

    /** @test */
    public function it_uses_the_decorator_when_enabled(): void
    {
        $ruleset = new class extends BaseRuleset{
            public function rules(): array
            {
                return ['x' => 'required|integer'];
            }
        };

        $validator = ValidatorFacade::makeFromRuleset($ruleset, ['x' => 123]);

        $this->assertTrue($validator->passes(), 'Expected decorator-based validator to pass');

        $validator = app(BaseFactoryContract::class)->makeFromRuleset($ruleset, ['x' => 123]);

        $this->assertTrue($validator->passes(), 'Expected decorator-based validator to pass');
    }

    /** @test */
    public function it_can_reference_the_model_and_validate_uniqueness(): void
    {
        $post1 = TestPost::create(['slug' => 'my-first-post']);

        $post2 = TestPost::create(['slug' => 'my-second-post']);

        $this->assertDatabaseHas('posts', ['slug' => 'my-first-post']);
        $this->assertDatabaseHas('posts', ['slug' => 'my-second-post']);

        $ruleset = new class($post1) extends BaseModelRuleset{
            protected static function modelClass(): string
            {
                return TestPost::class;
            }

            public function rules(): array
            {
                return ['slug' => 'required|unique:posts,slug,'.$this->model()->id];
            }
        };

        $validator = $ruleset->validator(['slug' => 'my-first-post']);

        $this->assertTrue($validator->passes(), 'Expected unique rule to pass for same model id');

        $validator = $ruleset::for($post2)->validator(['slug' => 'my-first-post']);

        $this->assertTrue($validator->fails(), 'Expected unique rule to fail for different model id');
    }

    /** @test */
    public function it_allows_custom_validation_rule_in_ruleset()
    {
        $ruleset = new class() extends BaseRuleset{
            public function rules(): array
            {
                return ['custom_field' => 'required|custom_rule'];
            }
        };

        ValidatorFacade::extend('custom_rule', function ($attribute, $value, $parameters, $validator) {
            return $value === 'valid';
        });

        $validator = $ruleset->validator(['custom_field' => 'valid']);
        $this->assertTrue($validator->passes(), 'Custom validation rule should pass');

        $validator = $ruleset->validator(['custom_field' => 'invalid']);
        $this->assertTrue($validator->fails(), 'Custom validation rule should fail');
    }

    /** @test */
    public function model_aware_ruleset_support_nullable_fields()
    {
        $post = TestPost::create(['slug' => 'my-first-post']);

        $ruleset = new class($post) extends BaseModelRuleset{
            protected static function modelClass(): string
            {
                return TestPost::class;
            }

            public function rules(): array
            {
                return ['slug' => 'nullable|unique:posts,slug'];
            }
        };

        $validator = $ruleset->validator(['slug' => null]);
        $this->assertTrue($validator->passes(), 'Nullable slug should pass validation when it is null');
    }

    /** @test */
    public function it_validates_multiple_fields_with_complex_rules()
    {
        $ruleset = new class() extends BaseRuleset{
            public function rules(): array
            {
                return [
                    'email' => 'required|email',
                    'name' => 'required|string|min:3|max:50',
                    'age' => 'required|integer|min:18',
                ];
            }
        };

        $validator = $ruleset->validator([
            'email' => 'foo@example.com',
            'name' => 'Sid',
            'age' => 30,
        ]);

        $this->assertTrue($validator->passes(), 'Expected complex rule validation to pass');
    }

    /** @test */
    public function it_validates_required_if_condition()
    {
        $ruleset = new class() extends BaseRuleset{
            public function rules(): array
            {
                return [
                    'email' => 'required|email',
                    'password' => 'required_with:email',
                ];
            }
        };

        $validator = $ruleset->validator(['email' => 'foo@example.com', 'password' => 'password']);
        $this->assertTrue($validator->passes(), 'Password is required when email is provided');

        $validator = $ruleset->validator(['email' => 'foo@example.com']);
        $this->assertTrue($validator->fails(), 'Password is required when email is provided and password is missing');
    }

    /** @test */
    public function it_returns_custom_error_messages()
    {
        $ruleset = new class() extends BaseRuleset{
            public function rules(): array
            {
                return ['email' => 'required|email'];
            }

            public function messages(): array
            {
                return ['email.required' => 'Email is mandatory'];
            }
        };

        $validator = $ruleset->validator([]);
        $this->assertTrue($validator->fails());
        $this->assertEquals('Email is mandatory', $validator->errors()->first('email'));
    }

    /** @test */
    public function it_returns_custom_attributes_in_error_messages()
    {
        $ruleset = new class() extends BaseRuleset{
            public function rules(): array
            {
                return ['email' => 'required|email'];
            }

            public function messages(): array
            {
                return [
                    'email.required' => ':attribute is mandatory',
                ];
            }

            public function attributes(): array
            {
                return ['email' => 'User Email'];
            }
        };

        $validator = $ruleset->validator([]);
        $this->assertTrue($validator->fails());

        $this->assertEquals('User Email is mandatory', $validator->errors()->first('email'));
    }
}
