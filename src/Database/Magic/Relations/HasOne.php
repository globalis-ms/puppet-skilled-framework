<?php
namespace Globalis\PuppetSkilled\Database\Magic\Relations;

use Globalis\PuppetSkilled\Database\Magic\Model;

class HasOne extends HasOneOrMany
{
    /**
     * Indicates if a default model instance should be used.
     *
     * Alternatively, may be a Closure to execute to retrieve default value.
     *
     * @var \Closure|array|bool
     */
    protected $withDefault;


    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return $this->query->first() ?: $this->getDefaultFor($this->parent);
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param  array   $models
     * @param  string  $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }

        return $models;
    }

    /**
     * Get the default value for this relation.
     *
     * @param  \Globalis\PuppetSkilled\Database\Magic\Model  $model
     * @return \Globalis\PuppetSkilled\Database\Magic\Model|null
     */
    protected function getDefaultFor(Model $model)
    {
        if (! $this->withDefault) {
            return;
        }

        $instance = $this->related->newInstance()->setAttribute(
            $this->getForeignKeyName(),
            $model->getAttribute($this->localKey)
        );

        if (is_callable($this->withDefault)) {
            return call_user_func($this->withDefault, $instance) ?: $instance;
        }

        if (is_array($this->withDefault)) {
            $instance->forceFill($this->withDefault);
        }

        return $instance;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param  array  $models
     * @param  array  $results
     * @param  string  $relation
     * @return array
     */
    public function match(array $models, array $results, $relation)
    {
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Return a new model instance in case the relationship does not exist.
     *
     * @param  \Closure|array|bool  $callback
     * @return $this
     */
    public function withDefault($callback = true)
    {
        $this->withDefault = $callback;

        return $this;
    }
}
