<?php
namespace Globalis\PuppetSkilled\Database\Magic\Revisionable;

use Carbon\Carbon;

trait Revisionable
{
    /**
     * Boot the trait for a model.
     */
    protected static function bootRevisionable()
    {
        static::observe(Listener::class);
    }

    protected function getRevisionableClassModel()
    {
        return (isset($this->revisionClassModel))? $this->revisionClassModel : '\Globalis\PuppetSkilled\Database\Magic\Revisionable\Revision';
    }

    /**
     * Determine if model has history at given timestamp if provided or any at all.
     *
     * @param \DateTime|string $timestamp DateTime|Carbon object or parsable date string @see strtotime()
     *
     * @return bool
     */
    public function hasHistory($timestamp = null)
    {
        if ($timestamp) {
            return (bool) $this->snapshot($timestamp);
        }

        return $this->revisions()->exists();
    }

    /**
     * Get an array of updated revisionable attributes.
     *
     * @return array
     */
    public function getDiff()
    {
        return array_diff_assoc($this->getNewAttributes(), $this->getOldAttributes());
    }

    /**
     * Get an array of original revisionable attributes.
     *
     * @return array
     */
    public function getOldAttributes()
    {
        $attributes = $this->prepareAttributes($this->original);
        $attributes = $this->getRevisionableItems($attributes);
        return $attributes;
    }

    /**
     * Get an array of current revisionable attributes.
     *
     * @return array
     */
    public function getNewAttributes()
    {
        $attributes = $this->prepareAttributes($this->attributes);
        $attributes = $this->getRevisionableItems($attributes);
        return $attributes;
    }

    /**
     * Stringify revisionable attributes.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function prepareAttributes(array $attributes)
    {
        foreach ($attributes as $key => $attribute) {
            if (in_array($key, $this->getDates()) && ! is_null($attribute)) {
                $attribute = $this->asDateTime($attribute);
            }
            if ($attribute instanceof DateTime) {
                $attribute = $this->fromDateTime($attribute);
            }
            $attributes[$key] = (string) $attribute;
        }
        return $attributes;
    }

    /**
     * Get an array of revisionable attributes.
     *
     * @param array $values
     *
     * @return array
     */
    protected function getRevisionableItems(array $values)
    {
        if (count($this->getRevisionable()) > 0) {
            return array_intersect_key($values, array_flip($this->getRevisionable()));
        }

        return array_diff_key($values, array_flip($this->getNonRevisionable()));
    }

    /**
     * Attributes being revisioned.
     *
     * @var array
     */
    public function getRevisionable()
    {
        return property_exists($this, 'revisionable') ? (array) $this->revisionable : [];
    }

    /**
     * Attributes hidden from revisioning if revisionable are not provided.
     *
     * @var array
     */
    public function getNonRevisionable()
    {
        return property_exists($this, 'nonRevisionable')
                ? (array) $this->nonRevisionable
                : ['created_at', 'updated_at', 'deleted_at'];
    }

    /**
     * Model has many Revisions.
     *
     * @return \Globalis\PuppetSkilled\Database\Relations\HasMany
     */
    public function revisions()
    {
        return $this->morphMany($this->getRevisionableClassModel(), 'revisionable', 'revisionable_type', 'row_id')
                    ->ordered();
    }

    /**
     * Model has one latestRevision.
     *
     * @return \Globalis\PuppetSkilled\Database\Relations\HasMany
     */
    public function latestRevision()
    {
        return $this->morphOne($this->getRevisionableClassModel(), 'revisionable', 'revisionable_type', 'row_id')
                    ->ordered();
    }
}
