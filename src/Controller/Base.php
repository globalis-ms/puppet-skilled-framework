<?php
namespace Globalis\PuppetSkilled\Controller;

use \Globalis\PuppetSkilled\View\View;
use \Globalis\PuppetSkilled\View\Asset;
use \Globalis\PuppetSkilled\Core\Application;

/**
 * Base Controller Class
 */
abstract class Base
{
    /**
     * Global data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Template to load
     *
     * @var string
     */
    protected $template;

    /**
     * The layout into which template will be rendered
     *
     * @var string
     */
    protected $layout;

    /**
     * Application instance
     *
     * @var \Globalis\PuppetSkilled\Core\Application
     */
    protected $application;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->application = Application::getInstance();
    }

    /**
     * Render function
     *
     * @param  array  $data    Variable names and their values to be passed into the template.
     * @param  string $template Template to load (r Default = $this->template or router->directory . router->classe . '/' . $router->method)
     * @return void
     */
    protected function render(array $data = [], $template = null)
    {
        if (!$template) {
            $template = $this->template;
        }

        $template = (!empty($template)) ? $template : $this->router->directory . $this->router->class . '/' . $this->router->method;
        $data = array_merge($this->data, $data);
        if ($this->input->is_ajax_request()) {
            $this->layout = false;
        } else {
            if (!isset($data['page_title'])) {
                $data['page_title'] = 'lang:' . $this->router->class . '_title_' . $this->router->method;
            }
        }
        $view = new View($this->layout);
        $view->set($data);
        $this->output->set_output($view->render($template));

        if ($this->layout && ($csp = config_item('csp_protection'))) {
            // Add CSP
            if (!isset($csp['script-src'])) {
                $csp['script-src'] = ['self'];
            }
            $asset = Asset::getInstance();
            foreach ($asset->getScriptNonces() as $nonce) {
                $csp['script-src'][] = "'nonce-".$nonce."'";
            }
            $string = '';
            foreach ($csp as $key => $values) {
                $string .= " " . $key . " " . implode(" ", $values) .";";
            }
            if ($report = config_item('csp_report')) {
                $string .= " report-uri " . $report;
            }
            if (config_item('csp_report_only')) {
                $this->output->set_header('Content-Security-Policy-Report-Only:'. $string);
                //var_dump($csp);die;
            } else {
                $this->output->set_header('Content-Security-Policy:'. $string);
            }
        }
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
        return $this->application->get($name);
    }
}
