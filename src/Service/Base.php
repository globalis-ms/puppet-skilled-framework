<?php
namespace Globalis\PuppetSkilled\Service;

use \Globalis\PuppetSkilled\Core\Application;

/**
 * Base Service Class
 */
abstract class Base
{
    /**
     * __get magic
     *
     * Allows controllers to access Application's container.
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return Application::getInstance()->get($name);
    }
}
