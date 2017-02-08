<?php
namespace Globalis\PuppetSkilled\Database\Magic\Revisionable;

use Globalis\PuppetSkilled\Database\Magic\Model;

class Revision extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'revisions';

    /**
     * Action executor user model.
     *
     * @var string
     */
    protected static $userModel;

    /**
     * Allow mass assignement.
     *
     * @var array
     */
    protected $fillable = [
        'table_name', 'action', 'user_id', 'user', 'old',
        'new', 'ip', 'ip_forwarded', 'created_at',
    ];

    public $timestamps = false;

    protected $dates = ['created_at'];

    /**
     * {@inheritdoc}
     */
    public static function boot()
    {
        parent::boot();

        // Make it read-only
        static::updating(function () {
            return false;
        });
    }

    /**
     * Revision belongs to User (action Executor).
     *
     * @return \Globalis\PuppetSkilled\Database\Magic\Relations\BelongsTo
     */
    public function executor()
    {
        return $this->belongsTo(static::$userModel, 'user_id');
    }

    /**
     * Revision morphs to models in revisioned_type.
     *
     * @return \Globalis\PuppetSkilled\Database\Magic\Relations\MorphTo
     */
    public function revisioned()
    {
        return $this->morphTo('revisioned', 'revisionable_type', 'revisionable_id');
    }

    /**
     * Get array of updated fields.
     *
     * @return array
     */
    public function getUpdated()
    {
        return array_keys(array_diff_assoc($this->new, $this->old));
    }

    /**
     * Get diff of the old/new arrays.
     *
     * @return array
     */
    public function getDiff()
    {
        $diff = [];

        foreach ($this->getUpdated() as $key) {
            $diff[$key]['old'] = $this->old($key);

            $diff[$key]['new'] = $this->new($key);
        }

        return $diff;
    }

    /**
     * Determine whether field was updated during current action.
     *
     * @param string $key
     *
     * @return bool
     */
    public function isUpdated($key)
    {
        return in_array($key, $this->getUpdated());
    }

    /**
     * Accessor for old property.
     *
     * @return array
     */
    public function getOldAttribute($old)
    {
        return (array) json_decode($old);
    }

    /**
     * Accessor for new property.
     *
     * @return array
     */
    public function getNewAttribute($new)
    {
        return (array) json_decode($new);
    }

    /**
     * Accessor for user property.
     *
     * @return array
     */
    public function getUserAttribute($user)
    {
        return (array) json_decode($user);
    }

    /**
     * Get single value from the new/old array.
     *
     * @param string $version
     * @param string $key
     *
     * @return string
     */
    protected function getFromArray($version, $key)
    {
        return array_get($this->{$version}, $key);
    }

    /**
     * Set user model.
     *
     * @param string $class
     */
    public static function setUserModel($class)
    {
        static::$userModel = $class;
    }

    /**
     * Query scope ordered.
     *
     * @param  \Globalis\PuppetSkilled\Database\Magic\Builder
     * @return \Globalis\PuppetSkilled\Database\Magic\Builder
     */
    public function scopeOrdered($query)
    {
        return $query->latest()->latest('id');
    }

    /**
     * Query scope for.
     *
     * @param \Globalis\PuppetSkilled\Database\Magic\Builder      $query
     * @param \Globalis\PuppetSkilled\Database\Magic\Model|string $table
     * @return \Globalis\PuppetSkilled\Database\Magic\Builder
     */
    public function scopeFor($query, $table)
    {
        if ($table instanceof Model) {
            $table = $table->getTable();
        }

        return $query->where('table_name', $table);
    }

    /**
     * Handle dynamic method calls.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (in_array($method, ['new', 'old'])) {
            array_unshift($parameters, $method);

            return call_user_func_array([$this, 'getFromArray'], $parameters);
        }

        if ($method == 'label') {
            return reset($parameters);
        }

        return parent::__call($method, $parameters);
    }
}
