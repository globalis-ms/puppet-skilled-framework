<?php
namespace Globalis\PuppetSkilled\Auth\Magic;

trait Securable
{
    public function scopeSecure($query, $capability)
    {
        foreach ($this->affectedBy() as $resourceType => $closure) {
            $resource = app()->authentificationService
                ->permissions()
                ->getResourcesType($capability, $resourceType);
            if ($resource && !$resource->allAccept()) {
                $closure($resource, $query);
            }
        }
    }
}
