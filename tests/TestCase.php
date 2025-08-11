<?php

namespace Sjshovan\RulesetValidation\Tests;

use Illuminate\Contracts\Validation\Factory;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Orchestra\Testbench\TestCase as Base;
use Sjshovan\RulesetValidation\RulesetValidationServiceProvider;

abstract class TestCase extends Base
{
    use RefreshDatabase;

    protected string $rulesetDir;

    protected function getPackageProviders($app)
    {
        return [RulesetValidationServiceProvider::class];
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->rulesetDir = app_path(__DIR__.'/../tests/tmp');

        // Clean & recreate the directory fresh each test
        $fs = new Filesystem();
        $fs->deleteDirectory($this->rulesetDir);
        $fs->makeDirectory($this->rulesetDir, 0777, true);
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('ruleset-validation', require __DIR__.'/../config/ruleset-validation.php');
        $app['config']->set('ruleset-validation.behavior.factory_decorator', true); // enable decorator for tests

        // DB: in-memory sqlite
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app->register(RulesetValidationServiceProvider::class, true);
    }

    protected function defineDatabaseMigrations()
    {
        // Minimal table used by a model-aware ruleset (e.g., unique slug)
        $this->app['db']->connection()->getSchemaBuilder()->create('posts', function (Blueprint $t) {
            $t->id();
            $t->string('slug')->unique();
            $t->timestamps();
        });
    }

    protected function tearDown(): void
    {
        // Clean up generated files
        $dir = $this->rulesetDir;

        if (is_dir($dir)) {
            $it = new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS);
            $files = new \RecursiveIteratorIterator($it, \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file) : @unlink($file);
            }
            @rmdir($dir);
        }

        parent::tearDown();
    }
}
