<?php

namespace Sjshovan\RulesetValidation\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * Use `instantiateModel($model)` in constructors to initialize the model,
 * and `model()` to retrieve the bound instance.
 *
 * @throws \LogicException If an invalid model class or instance is used.
 * @throws \BadMethodCallException If `modelClass()` is not overridden.
 */
trait InstantiatesModel
{
    private ?Model $model = null;

    protected static function modelClass(): string
    {
        throw new \BadMethodCallException('Override modelClass() in '.static::class);
    }

    private function assertValidModelClass(): void
    {
        if (! is_a(static::modelClass(), Model::class, true)) {
            throw new \LogicException(
                sprintf('Model class must be an instance of %s.',
                    Model::class));
        }
    }

    private function assertValidModelInstance(Model $model): void
    {
        $modelClass = static::modelClass();

        if (! $model instanceof $modelClass) {
            throw new \LogicException(sprintf(
                'Model instance must be an instance of %s.',
                $modelClass));
        }
    }

    private function makeModel(): Model
    {
        $this->assertValidModelClass();

        return new (static::modelClass())();
    }

    private function setModel(Model $model): void
    {
        $this->assertValidModelInstance($model);

        $this->model = $model;
    }

    private function instantiateModel(?Model $model = null): void
    {
        $model = $model ?? $this->makeModel();

        $this->setModel($model);
    }

    final public function model(): Model
    {
        return $this->model ?? throw new \LogicException(
            sprintf('%s must be instantiated with a model before calling model().', static::class)
        );
    }
}