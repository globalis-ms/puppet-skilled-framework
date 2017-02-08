<?php
namespace Globalis\PuppetSkilled\Auth\Acl;

use \Globalis\PuppetSkilled\Auth\Resource;

class Node
{
    protected $name;

    protected $children = [];

    protected $resources = [];

    protected $isAllowed;

    public function __construct($name, $isAllowed)
    {
        $this->name = $name;
        $this->isAllowed = $isAllowed;
    }

    public function getName()
    {
        return $this->name;
    }

    public function allow(\SplQueue $queue, array $resources)
    {
        if (!$queue->isEmpty()) {
            $child = $queue->dequeue();
            if (!isset($this->children[$child])) {
                $this->children[$child] = new Node($child, false);
            }
            $this->children[$child]->allow($queue, $resources);
        } else {
            $this->isAllowed = true;
            foreach ($resources as $resource) {
                $this->addResource($resource);
            }
        }
    }

    public function getResourcesType(\SplQueue $acl, $resourceType)
    {
        $resources = $this->resource($resourceType);
        if (!$acl->isEmpty()) {
            $child = $acl->dequeue();
            if (isset($this->children[$child])) {
                if ($childResources = $this->children[$child]->getResourcesType($acl, $resourceType)) {
                    // Merge with ours resources
                    if ($resources !== null) {
                        $resources = clone $resources;
                        $resources->union($childResources);
                    } else {
                        $resources = $childResources;
                    }
                }
            }
        }
        return $resources;
    }

    public function isAllowed(\SplQueue $acl, $resourceType = null, $resourceId = null)
    {

        if ($this->isAllowed && (!$resourceId || $this->hasResource($resourceType, $resourceId))) {
            return true;
        }

        if (!$acl->isEmpty()) {
            $rightKey = $acl->dequeue();

            if ($rightKey == '*') {
                foreach ($this->children as $child) {
                    if ($child->isAllowed(clone $acl, $resourceType, $resourceId)) {
                        return true;
                    }
                }
            } elseif (isset($this->children[$rightKey])) {
                return $this->children[$rightKey]->isAllowed(clone $acl, $resourceType, $resourceId);
            }
        }
        return false;
    }

    public function addChild(Node $node)
    {
        $this->children[$node->getName()] = $node;
        return $this;
    }

    public function getChildren()
    {
        return $this->children;
    }

    public function addResource(Resource $resource)
    {
        $type = get_class($resource);
        if (!isset($this->resources[$type])) {
            $this->resources[$type] = $resource;
        } else {
            $this->resources[$type]->union($resource);
        }
        return $this;
    }

    public function hasResource($type, $id)
    {
        // if ressource type doesn't exist resource exit
        return (isset($this->resources[$type]) ? $this->resources[$type]->has($id) : false);
    }

    public function resource($type = null)
    {
        if ($type === null) {
            return $this->resources;
        } elseif (isset($this->resources[$type])) {
            return $this->resources[$type];
        }
        return null;
    }
}
