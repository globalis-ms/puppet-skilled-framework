<?php
namespace Globalis\PuppetSkilled\Auth\Magic;

trait Securable
{
    public function scopeSecure($query, $capability)
    {
        foreach ($this->affectedBy() as $resourceType => $closure) {
            $resource = app()->authenticationService
                ->permissions()
                ->getResourcesType($capability, $resourceType);
            if ($resource && !$resource->allAccept()) {
                $closure($resource, $query);
            }
        }
    }
}
