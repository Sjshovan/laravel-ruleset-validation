<?php

namespace Sjshovan\RulesetValidation\Tests\Feature;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Support\Facades\Validator;
use Sjshovan\RulesetValidation\Abstracts\BaseRuleset;
use Sjshovan\RulesetValidation\Factory as RulesetFactory;
use Sjshovan\RulesetValidation\Tests\TestCase;

class ConcreteFactoryTest extends TestCase
{
    protected string $factoryMode = 'concrete';

    public function test_concrete_factory_preserves_extensions_registered_after_the_swap(): void
    {
        $this->assertInstanceOf(RulesetFactory::class, app(Factory::class));
        Validator::extend('approved', fn ($attribute, $value) => $value === 'yes');

        $ruleset = new class extends BaseRuleset {
            public function rules(): array
            {
                return ['review' => 'required|approved'];
            }
        };

        $this->assertTrue(app(Factory::class)->makeFromRuleset($ruleset, ['review' => 'yes'])->passes());
        $this->assertTrue($ruleset->validator(['review' => 'no'])->fails());
    }
}
