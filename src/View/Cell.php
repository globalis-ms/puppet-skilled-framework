<?php
namespace Globalis\PuppetSkilled\View;

use \Globalis\PuppetSkilled\Core\Application;

/**
 * View Cell Base Class
 */
abstract class Cell
{
    /**
     * List of valid options
     *
     * @var array
     */
    protected $validCellOptions = [];

    /**
     * Constructor
     *
     * @param array $cellOptions @see $validCellOptions
     */
    public function __construct(array $cellOptions)
    {
        foreach ($this->validCellOptions as $var) {
            if (isset($cellOptions[$var])) {
                $this->{$var} = $cellOptions[$var];
            }
        }
    }

    /**
     * Rendeing the cell
     *
     * @param  string $template
     * @param  array $data
     * @return string
     */
    protected function render($template, $data = [])
    {
        $view = new View();
        $view->type(View::VIEW_TYPE_CELL);
        $view->set($data);
        $className = get_class($this);
        $namePrefix = '\View\Cell\\';
        $name = substr($className, strpos($className, $namePrefix) + strlen($namePrefix));
        $view->context(str_replace('\\', DIRECTORY_SEPARATOR, $name));
        return $view->render($template);
    }

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
