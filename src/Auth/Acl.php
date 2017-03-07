<?php
namespace Globalis\PuppetSkilled\Auth;

use \Globalis\PuppetSkilled\Auth\Acl\Node;

class Acl
{
    protected $root;

    public function __construct()
    {
        $this->root = new Node('root', false);
    }

    public function isAllowed($acl, $resourceType = null, $resourceValue = null)
    {
        $queue = $this->stringToQueue($acl);
        return $this->root->isAllowed($queue, $resourceType, $resourceValue);
    }

    public function allow($capability, array $resources)
    {
        $queue = $this->stringToQueue($capability);
        $this->root->allow($queue, $resources);
    }

    public function getResourcesType($capability, $resourceType)
    {
        $queue = $this->stringToQueue($capability);
        return $this->root->getResourcesType($queue, $resourceType);
    }

    protected function stringToQueue($str)
    {
        $segments = explode('.', $str);
        $queue = new \SplQueue();
        foreach ($segments as $segment) {
            $queue->enqueue($segment);
        }
        return $queue;
    }
}
