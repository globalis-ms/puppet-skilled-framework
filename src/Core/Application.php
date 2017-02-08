<?php
namespace Globalis\PuppetSkilled\Core;

require_once __DIR__ . '/helpers.php';

/**
 * Main Application Class
 * Use for load helper, library, services...
 */
class Application
{
    /**
     * Reference to the Application singleton
     *
     * @var     \Globalis\PuppetSkilled\Core\Application
     */
    protected static $instance;

    /**
     * Container object
     *
     * @var \Globalis\PuppetSkilled\Core\Container
     */
    protected $container;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->container = new Container();
        $this->wrapCodeigniter();
        self::$instance = $this;
    }

    /**
     * Initialize Container Ci
     *
     * @return void
     */
    protected function wrapCodeigniter()
    {
        $this->container['CI'] = function () {
            require_once BASEPATH.'core/Controller.php';
            return new \CI_Controller();
        };
    }

    /**
     * Get Container
     *
     * @return \Globalis\PuppetSkilled\Core\Container
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Access to CI data or container data
     *
     * @param  string $name
     * @return void
     */
    public function get($name)
    {
        if (isset($this->container['CI']->{$name})) {
            return $this->container['CI']->{$name};
        }
        return $this->container[$name];
    }

    /**
     * Get the Application singleton
     *
     * @return \Globalis\PuppetSkilled\Core\Application
     */
    public static function getInstance()
    {
        if (is_null(static::$instance)) {
            new static();
        }
        return static::$instance;
    }

    /**
     * Helper for building Application class with App namespace
     *
     * @param  string $class
     * @param  string $type
     * @return string
     */
    public static function className($class, $type)
    {
        if (strpos($class, '\\') !== false) {
            return $class;
        }
        return '\\App\\' . str_replace('/', '\\', $type . '\\' . $class);
    }

    /**
     * __get magic
     *
     * Allows to access Application's container.
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        return $this->get($name);
    }
}
