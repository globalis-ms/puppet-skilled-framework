<?php
namespace Globalis\PuppetSkilled\View;

trait ViewVarsTrait
{
    /**
     * Variables for the view
     *
     * @var array
     */
    public $viewVars = [];

    /**
     * Set a view var
     *
     * @param string|array $name
     * @param mixed $value
     */
    public function set($name, $value = null)
    {
        if (is_array($name)) {
            if (is_array($value)) {
                $data = array_combine($name, $value);
            } else {
                $data = $name;
            }
        } else {
            $data = [$name => $value];
        }
        $this->viewVars = $data + $this->viewVars;
        return $this;
    }

    /**
     * Fetch a view var
     *
     * @param  string $name
     * @param  mixed $default
     * @return mixed
     */
    public function fetch($name, $default = '')
    {
        return (isset($this->viewVars[$name])) ? $this->viewVars[$name] : $default;
    }
}
