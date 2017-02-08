<?php
namespace Globalis\PuppetSkilled\Auth;

abstract class Resource
{
    const ALL = 'all';

    protected $resources;

    public function union(Resource $resource)
    {
        if ($this->allAccept() || $resource->allAccept()) {
            $this->resources = [Resource::ALL];
        } else {
            $this->resources = array_merge($this->getResources(), $resource->getResources());
        }
    }

    abstract public function buildFromUserHasRoleid($id);

    public function has($id)
    {
        return (in_array(self::ALL, $this->getResources()) || in_array($id, $this->getResources()));
    }

    public function allAccept()
    {
        return in_array(Resource::ALL, $this->getResources());
    }

    public function getResources()
    {
        return $this->resources;
    }

    public function acceptAll()
    {
        $this->resources = [self::ALL];
    }
}
