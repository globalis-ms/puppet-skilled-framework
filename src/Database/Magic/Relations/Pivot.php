<?php
namespace Globalis\PuppetSkilled\Database\Magic\Relations;

use Globalis\PuppetSkilled\Database\Magic\Model;
use Globalis\PuppetSkilled\Database\Magic\Builder;

class Pivot extends Model
{
    /**
     * The parent model of the relationship.
     *
     * @var \Globalis\PuppetSkilled\Database\Magic\Model
     */
    protected $parent;

    /**
     * The name of the foreign key column.
     *
     * @var string
     */
    protected $foreignKey;

    /**
     * The name of the "other key" column.
     *
     * @var string
     */
    protected $relatedKey;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be loaded into the pivot model.
     *
     * @var array
     */
    protected $include = [];

    /**
     * Create a new pivot model instance.
     *
     * @param  array   $attributes
     * @param  \Globalis\PuppetSkilled\Database\Magic\Model  $parent
     * @param  string  $table
     * @param  bool    $exists
     * @return void
     */
    public function __construct($attributes = [], Model $parent = null, $table = '', $exists = false)
    {
        parent::__construct();

        if ($parent !== null) {
            // The pivot model is a "dynamic" model since we will set the tables dynamically
            // for the instance. This allows it work for any intermediate tables for the
            // many to many relationship that are defined by this developer's classes.
            $this->setConnection($parent->getConnectionName())
                ->setTable($table)
                ->forceFill($attributes)
                ->syncOriginal();

            // We store off the parent instance so we will access the timestamp column names
            // for the model, since the pivot model timestamps aren't easily configurable
            // from the developer's point of view. We can use the parents to get these.
            $this->parent = $parent;

            $this->exists = $exists;

            $this->timestamps = $this->hasTimestampAttributes();
        }
    }

    /**
     * Create a new instance of the given model.
     *
     * @param  array  $attributes
     * @param  bool  $exists
     * @return static
     */
    public function newInstance($attributes = [], $exists = false)
    {
        $model = new static((array) $attributes, $this->parent, $this->table, $exists);
        $model->setPivotKeys($this->getForeignKey(), $this->getRelatedKey());
        return $model;
    }

    /**
     * Create a new pivot model from raw values returned from a query.
     *
     * @param  \Globalis\PuppetSkilled\Database\Magic\Model  $parent
     * @param  array   $attributes
     * @param  string  $table
     * @param  bool    $exists
     * @return static
     */
    public static function fromRawAttributes(Model $parent, $attributes, $table, $exists = false)
    {
        $instance = new static($attributes, $parent, $table, $exists);

        $instance->setRawAttributes($attributes, true);

        return $instance;
    }

    /**
     * Set the keys for a save update query.
     *
     * @param  \Globalis\PuppetSkilled\Database\Magic\Builder  $query
     * @return \Globalis\PuppetSkilled\Database\Magic\Builder
     */
    protected function setKeysForSaveQuery(Builder $query)
    {
        $query->where($this->foreignKey, $this->getAttribute($this->foreignKey));

        return $query->where($this->relatedKey, $this->getAttribute($this->relatedKey));
    }

    /**
     * Delete the pivot model record from the database.
     *
     * @return int
     */
    public function delete()
    {
        return $this->getDeleteQuery()->delete();
    }

    /**
     * Get the query builder for a delete operation on the pivot.
     *
     * @return \Globalis\PuppetSkilled\Database\Magic\Builder
     */
    protected function getDeleteQuery()
    {
        return $this->newQuery()->where([
            $this->foreignKey => $this->getAttribute($this->foreignKey),
            $this->relatedKey => $this->getAttribute($this->relatedKey),
        ]);
    }

    /**
     * Get the foreign key column name.
     *
     * @return string
     */
    public function getForeignKey()
    {
        return $this->foreignKey;
    }

    /**
     * Get the "other key" column name.
     *
     * @return string
     */
    public function getRelatedKey()
    {
        return $this->relatedKey;
    }

    /**
     * Get the "related key" column name.
     *
     * @return string
     */
    public function getOtherKey()
    {
        return $this->getRelatedKey();
    }

    /**
     * Set the key names for the pivot model instance.
     *
     * @param  string  $foreignKey
     * @param  string  $relatedKey
     * @return $this
     */
    public function setPivotKeys($foreignKey, $relatedKey)
    {
        $this->foreignKey = $foreignKey;

        $this->relatedKey = $relatedKey;

        return $this;
    }

    /**
     * Determine if the pivot model has timestamp attributes.
     *
     * @return bool
     */
    public function hasTimestampAttributes()
    {
        return array_key_exists($this->getCreatedAtColumn(), $this->attributes);
    }

    /**
     * Get the name of the "created at" column.
     *
     * @return string
     */
    public function getCreatedAtColumn()
    {
        return $this->parent->getCreatedAtColumn();
    }

    /**
     * Get the name of the "updated at" column.
     *
     * @return string
     */
    public function getUpdatedAtColumn()
    {
        return $this->parent->getUpdatedAtColumn();
    }
}
