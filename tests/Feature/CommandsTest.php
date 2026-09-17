<?php

namespace Sjshovan\RulesetValidation\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Sjshovan\RulesetValidation\Tests\TestCase;

class CommandsTest extends TestCase
{
    public function test_it_generates_and_lists_a_standard_ruleset(): void
    {
        config(['ruleset-validation.behavior.convention.intermediate_abstracts' => false]);

        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, $this->artisan('make:ruleset', ['ruleset' => 'GeneratedExample']));
        $path = $this->rulesetDir.'/GeneratedExampleRuleset.php';
        $this->assertFileExists($path, Artisan::output());
        $this->assertStringContainsString('extends BaseRuleset', file_get_contents($path));

        $this->assertSame(0, $this->artisan('ruleset:list'));
        $this->assertStringContainsString('Found: 1', Artisan::output());
    }

    public function test_dry_run_does_not_write_a_ruleset(): void
    {
        config(['ruleset-validation.behavior.convention.intermediate_abstracts' => false]);

        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, $this->artisan('make:ruleset', ['ruleset' => 'PreviewExample', '--dry-run' => true]));
        $this->assertStringContainsString('class PreviewExampleRuleset extends BaseRuleset', Artisan::output());
        $this->assertFileDoesNotExist($this->rulesetDir.'/PreviewExampleRuleset.php');
    }

    public function test_it_generates_a_model_ruleset(): void
    {
        config(['ruleset-validation.behavior.convention.intermediate_abstracts' => false]);

        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, $this->artisan('make:ruleset', ['ruleset' => 'ModelExample', '--model' => 'Illuminate\\Database\\Eloquent\\Model']));
        $path = $this->rulesetDir.'/ModelExampleRuleset.php';
        $this->assertFileExists($path, Artisan::output());
        $this->assertStringContainsString('extends BaseModelRuleset', file_get_contents($path));
    }

    public function test_it_generates_an_intermediate_abstract(): void
    {
        $this->withoutMockingConsoleOutput();
        $this->assertSame(0, $this->artisan('make:ruleset', ['ruleset' => 'IntermediateExample', '--gen' => 'yes']));
        $this->assertFileExists($this->rulesetDir.'/RulesetAbstract.php', Artisan::output());
        $this->assertStringContainsString('extends RulesetAbstract', file_get_contents($this->rulesetDir.'/IntermediateExampleRuleset.php'));
    }
}
