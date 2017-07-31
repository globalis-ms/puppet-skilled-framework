<?php
namespace Globalis\PuppetSkilled\Library;

use \Globalis\PuppetSkilled\Core\Application;

/**
 * Query Filter Class
 */
class QueryFilter
{
    /**
     * Application Instance
     *
     * @var \Globalis\PuppetSkilled\Core\Application
     */
    protected $application;

    /**
     * Default options
     *
     * @var array
     */
    protected $defaultOptions = [
        'action' => 'action',
        'method' => 'get',
        'filter_action' => 'filter',
        'reset_action' => 'reset',
        'default_filters' => [],
        'filters' => [],//associative array ['input_name' => ['column' => col_list, 'db_function' => 'db_function']]
        'save' => false,//save entry in session ? false = no save else contexte name
    ];

    /**
     * Params
     *
     * @var array
     */
    public $params = [];

    /**
     * Build options
     *
     * @var array
     */
    protected $options;

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct($options)
    {
        $this->application = Application::getInstance();
        $this->options = $this->mergeOptions($options);
    }

    /**
     * Merge options with default options
     *
     * @param  array $options
     * @return array
     */
    protected function mergeOptions(array $options)
    {
        $defaults = $options + $this->defaultOptions;
        $defaults['params'] = [];
        if (!$defaults['filter_action'] || $this->application->input->{$defaults['method']}($defaults['action']) === $defaults['filter_action']) {
            //Action filter
            //cleaning post/get data
            foreach ($defaults['filters'] as $params => $column) {
                switch ($defaults['method']) {
                    case 'get':
                        if (isset($_GET[$params])) {
                            if (is_array($_GET[$params])) {
                                $_GET[$params] = array_filter(
                                    $_GET[$params],
                                    function ($value) {
                                        return (trim($value) !== '');
                                    }
                                );
                                if (empty($_GET[$params])) {
                                    unset($_GET[$params]);
                                }
                            } elseif (trim($_GET[$params]) === '') {
                                unset($_GET[$params]);
                            }
                        }
                        break;
                    case 'post':
                        if (isset($_POST[$params]) && trim($_POST[$params]) === '') {
                            if (is_array($_POST[$params])) {
                                $_POST[$params] = array_filter(
                                    $_POST[$params],
                                    function ($value) {
                                        return (trim($value) !== '');
                                    }
                                );
                                if (empty($_POST[$params])) {
                                    unset($_POST[$params]);
                                }
                            } elseif (trim($_POST[$params]) === '') {
                                unset($_POST[$params]);
                            }
                            unset($_POST[$params]);
                        }
                        break;
                }
            }
            $defaults['params'] = array_intersect_key($this->application->input->{$defaults['method']}(), $defaults['filters']);
        } elseif ($this->application->input->{$defaults['method']}($defaults['action']) === $defaults['reset_action']) {
            foreach ($defaults['filters'] as $params => $callback) {
                switch ($defaults['method']) {
                    case 'get':
                        unset($_GET[$params]);
                        break;
                    case 'post':
                        unset($_POST[$params]);
                        break;
                }
            }
            //Reset action
            $defaults['params'] = $defaults['default_filters'];
        } elseif ($defaults['save']) {
            $defaults['params'] = $this->application->session->userdata($options['save']);
        }

        // Si aucun filtre n'a été soumis (session non initialisé, filtre par défaut
        $session_exist = false;
        if ($defaults['save']) {
            $session_exist =  $this->application->session->has_userdata($options['save']);
        }
        if (empty($defaults['params']) && !$session_exist) {
             $defaults['params'] = $defaults['default_filters'];
        }
        return $defaults;
    }

    /**
     * Has active filter
     *
     * @return boolean
     */
    public function hasActiveFilters()
    {
        return !empty($this->options['params']);
    }

    /**
     * Get filter acton name
     *
     * @return string
     */
    public function getActionName()
    {
        return $this->options['action'];
    }

    /**
     * Get filter action value
     *
     * @return string
     */
    public function getFilterActionValue()
    {
        return $this->options['filter_action'];
    }

    /**
     * Get reset action value
     *
     * @return string
     */
    public function getResetActionValue()
    {
        return $this->options['reset_action'];
    }

    /**
     * Get filter field value
     *
     * @param  string $name
     * @return mixed
     */
    public function getValue($name)
    {
        return (isset($this->options['params'][$name])) ? $this->options['params'][$name] : null;
    }

    /**
     * Apply filter on query
     *
     * @param  \Globalis\PuppetSkilled\Database\Query\Builder|\Globalis\PuppetSkilled\Database\Magic\Builder $query
     * @return \Globalis\PuppetSkilled\Database\Query\Builder
     */
    public function run($query)
    {
        $options = $this->options;
        if ($this->hasActiveFilters()) {
            foreach ($options['params'] as $param => $value) {
                $query->where(
                    function ($query) use ($options, $param, $value) {
                        $options['filters'][$param]($query, $value);
                    }
                );
            }
        }
        //Setting data for filter form
        $this->options['params'] = $options['params'];

        if ($options['save']) {
            $_SESSION[$options['save']] = $options['params'];
        }
        return $query;
    }
}
