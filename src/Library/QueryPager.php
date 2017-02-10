<?php
namespace Globalis\PuppetSkilled\Library;

use Globalis\PuppetSkilled\Core\Application;

/**
 * Query Pager Class
 */
class QueryPager
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
    protected $_default_options = [
        'request_param' => [
            'page' => 'page',
            'order' => 'order',
            'direction' => 'direction',
            'limit' => 'pagesize'
        ],
        'sort' => [],//possible sort column
        'page'  => 1,
        'limit' => 10,
        'limit_choices' => null,//possible limit leave empty for all
        'save' => false,//save entry in session ? false = no save else contexte name
        'unique_order_key' => 'id', // Unique order key
    ];

    /**
     * Build options
     *
     * @var array
     */
    protected $options;

    /**
     * Pager result
     *
     * @var array
     */
    protected $result;

    /**
     * Constructor
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->application = Application::getInstance();
        $this->options = $this->mergeOptions($options);
    }

    /**
     * Merge options with default options
     *
     * @param  array  $options
     * @return array
     */
    protected function mergeOptions(array $options)
    {
        $defaults = $options + $this->_default_options;
        $flip_request_params = array_flip($defaults['request_param']);
        $request = array_intersect_key($this->application->input->get(), $flip_request_params);

        if ($defaults['save'] !== false) {
            $last_request = $this->application->session->userdata($defaults['save']);
            if (!empty($last_request)) {
                $request = array_merge($last_request, $request);
            }
            $this->application->session->set_userdata($defaults['save'], $request);
        }

        foreach ($request as $key => $value) {
            if (isset($defaults[$flip_request_params[$key]])) {
                $defaults[$flip_request_params[$key]] = $value;
            }
        }
        return array_merge($defaults, $request);
    }

    /**
     * Is sortable field
     *
     * @param  string  $fieldName
     * @return boolean
     */
    public function isSortable($fieldName)
    {
        return isset($this->options['sort'][$fieldName]);
    }

    /**
     * Get pager result
     *
     * @return array
     */
    public function getResult()
    {
        return $this->result;
    }

    /**
     * Apply pager on query
     *
     * @param  \Globalis\PuppetSkilled\Database\Query\Builder|\Globalis\PuppetSkilled\Database\Magic\Builder $query
     * @return array
     */
    public function run($query)
    {
        $options = $this->options;
        $count = $query->getCountForPagination();

        if (!$options['limit']) {
            $options['page'] = 1;
        } elseif ($options['page'] < 1 || $options['page'] > ceil($count / $options['limit'])) {
            $options['page'] = 1;
        }

        $order = false;
        $direction = false;
        if (!empty($options['order'])) {
            if (is_array($options['order'])) {
                foreach ($options['order'] as $order_column) {
                    if (!empty($options['sort'][$order_column])) {
                        $order = $order_column;
                        if (isset($options['direction']) && strtolower($options['direction']) == 'desc') {
                            $direction = 'desc';
                            $query->orderBy($options['sort'][$order_column], 'DESC', true);
                        } else {
                            $direction = 'asc';
                            $query->orderBy($options['sort'][$order_column], 'ASC', true);
                        }
                    }
                }
            } else {
                if (!empty($options['sort'][$options['order']])) {
                    $order = $options['order'];
                    if (isset($options['direction']) && strtolower($options['direction']) == 'desc') {
                        $direction = 'desc';
                        $query->orderBy($options['sort'][$options['order']], 'DESC', true);
                    } else {
                        $direction = 'asc';
                        $query->orderBy($options['sort'][$options['order']], 'ASC', true);
                    }
                }
            }
        }
        // Add order by unique key
        if ($query instanceof \Globalis\PuppetSkilled\Database\Magic\Builder) {
            $isOrdering = !empty($query->getQuery()->orders);
        } else {
            $isOrdering = !empty($query->orders);
        }

        if ($isOrdering) {
            $query->orderBy($options['unique_order_key']);
        }

        if (!empty($options['limit_choices']) && !in_array($options['limit'], $options['limit_choices'])) {
            $options['limit'] = current($options['limit_choices']);
        }
        if (!empty($options['limit'])) {
            $query->limit($options['limit']);
            $query->offset(($options['page']-1) * $options['limit']);
        }

        $page = $options['page'];
        $limit = $options['limit'];

        if (!$options['limit']) {
            $pageCount = 1;
        } else {
            $pageCount = (int)ceil($count / $limit);
        }
        $this->result =  [
            'result'        => $query->get(),
            'page'          => $page,
            'total'         => $count,
            'perPage'       => $limit,
            'prevPage'      => ($page > 1),
            'nextPage'      => ($count > ($page * $limit)),
            'pageCount'     => $pageCount,
            'sort'          => $order,
            'direction'     => $direction,
            'limit'         => $limit,
            'limit_choices' => $options['limit_choices'],
        ];
        return $this->result;
    }
}
