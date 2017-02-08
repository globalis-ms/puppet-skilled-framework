<?php
namespace Globalis\PuppetSkilled\View;

use \Globalis\PuppetSkilled\Core\Application;

/**
 * View Class
 */
class View
{
    use ViewVarsTrait;

    const VIEW_TYPE_LAYOUT = 'Layout';

    const VIEW_TYPE_TEMPLATE = 'Template';

    const VIEW_TYPE_CELL = 'Cell';

    const VIEW_TYPE_ELEMENT = 'Element';

    /**
     * Asset instance
     *
     * @var \Globalis\PuppetSkilled\View\Asset
     */
    protected $asset;

    /**
     * View path
     *
     * @var array
     */
    protected $paths = [VIEWPATH];

    /**
     * Layout
     *
     * @var boolean|string
     */
    protected $layout;

    /**
     * View type
     *
     * @var string
     */
    protected $type;

    /**
     * Context path
     *
     * @var string
     */
    protected $context;

    /**
     * Constructor
     *
     * @param string $layout Layout to load
     */
    public function __construct($layout = null)
    {
        if ($layout) {
            Application::getInstance()->config->load('template', true);
            $this->asset = new Asset(Application::getInstance()->config->item($layout, 'template'));
            $this->layout = $layout;
        } else {
            $this->asset = Asset::getInstance();
        }
        $this->type(self::VIEW_TYPE_TEMPLATE);
    }

    /**
     * Type accesser
     *
     * @param  string $type
     * @return sting|\Globalis\PuppetSkilled\View\View
     */
    public function type($type = null)
    {
        if ($type !== null) {
            $this->type = $type;
            return $this;
        }
        return $this->type;
    }

    /**
     * Context accesser
     *
     * @param  string $context
     * @return string|\Globalis\PuppetSkilled\View\View
     */
    public function context($context = null)
    {
        if ($context !== null) {
            $this->context = $context;
            return $this;
        }
        return $this->context;
    }

    /**
     * Load a cell
     *
     * @param  string $cell
     * @param  array $data      Data for cell constructor
     * @param  array $options   Options for cell method
     * @return mixed
     */
    public function cell($cell, array $data = [], array $options = [])
    {
        $parts = explode('::', $cell);
        if (count($parts) === 2) {
            list($cell, $action) = [$parts[0], $parts[1]];
        } else {
            list($cell, $action) = [$parts[0], 'display'];
        }
        $className = Application::className($cell, 'View/' . self::VIEW_TYPE_CELL);
        $class = new $className($data);
        return $class->{$action}($options);
    }

    /**
     * Load a block (View in View)
     *
     * @param  string $name
     * @param  array $data
     * @return string
     */
    public function block($name, array $data = [])
    {
        $view = new View();
        $view->type($this->type());
        $view->context($this->context());
        $view->set(array_merge($this->viewVars, $data));
        return $view->render($name);
    }

    /**
     * Load an element
     *
     * @param  string $name
     * @param  array  $data
     * @return string
     */
    public function element($name, array $data = [])
    {
        $view = new View();
        $view->type(self::VIEW_TYPE_ELEMENT);
        $view->set(array_merge($this->viewVars, $data));
        return $view->render($name);
    }

    /**
     * Render view
     *
     * @param  string $view
     * @return string
     */
    public function render($view)
    {
        if (!$this->context() && strpos($view, '/') !== false) {
            $this->context(dirname($view));
        }
        $content = $this->viewContent($this->type(), DIRECTORY_SEPARATOR . $view);
        if ($this->layout) {
            $ext = pathinfo($this->layout, PATHINFO_EXTENSION);
            $this->layout = ($ext === '') ? $this->layout.'.php' : $this->layout;
            $this->set('content', $content);
            $content = $this->viewContent(self::VIEW_TYPE_LAYOUT, $this->layout);
        }
        return $content;
    }

    /**
     * Render a view content
     *
     * @param  string $type
     * @param  string $view
     * @return string
     */
    protected function viewContent($type, $view)
    {
        $ext = pathinfo($view, PATHINFO_EXTENSION);
        $view = ($ext === '') ? $view.'.php' : $view;
        foreach ($this->paths as $path) {
            $path .= $type . DIRECTORY_SEPARATOR;
            if ($this->context()
                &&
                file_exists($path . trim($this->context(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $view)
            ) {
                $viewPath = $path . trim($this->context(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $view;
                break;
            }
            if (file_exists($path . $view)) {
                $viewPath = $path . $view;
                break;
            }
        }
        if (!isset($viewPath)) {
            throw new \RuntimeException('Unable to find the ' . $view);
        }
        ob_start();
        include($viewPath);
        $content = ob_get_contents();
        ob_end_clean();
        return $content;
    }
}
