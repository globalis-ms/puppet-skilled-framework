<?php
namespace Globalis\PuppetSkilled\Database\Query\Grammar;

use Globalis\PuppetSkilled\Database\Query\Builder;

class MySqlGrammar extends \Globalis\PuppetSkilled\Database\Query\Grammar
{
    /**
     * The components that make up a select clause.
     *
     * @var array
     */
    protected $selectComponents = [
        'aggregate',
        'columns',
        'from',
        'joins',
        'wheres',
        'groups',
        'havings',
        'orders',
        'limit',
        'offset',
        'lock',
    ];

    /**
     * Compile a select query into SQL.
     *
     * @param  \Globalis\PuppetSkilled\Database\Query\Builder  $query
     * @return string
     */
    public function compileSelect(Builder $query)
    {
        $sql = parent::compileSelect($query);

        if ($query->unions) {
            $sql = '('.$sql.') '.$this->compileUnions($query);
        }

        return $sql;
    }

    /**
     * Compile a single union statement.
     *
     * @param  array  $union
     * @return string
     */
    protected function compileUnion(array $union)
    {
        $joiner = $union['all'] ? ' union all ' : ' union ';

        return $joiner.'('.$union['query']->toSql().')';
    }

    /**
     * Compile the random statement into SQL.
     *
     * @param  string  $seed
     * @return string
     */
    public function compileRandom($seed)
    {
        return 'RAND('.$seed.')';
    }

    /**
     * Compile the lock into SQL.
     *
     * @param  \Globalis\PuppetSkilled\Database\Query\Builder  $query
     * @param  bool|string  $value
     * @return string
     */
    protected function compileLock(Builder $query, $value)
    {
        if (is_string($value)) {
            return $value;
        }

        return $value ? 'for update' : 'lock in share mode';
    }

    /**
     * Compile an update statement into SQL.
     *
     * @param  \Globalis\PuppetSkilled\Database\Query\Builder  $query
     * @param  array  $values
     * @return string
     */
    public function compileUpdate(Builder $query, $values)
    {
        $table = $this->wrapTable($query->from);

        $columns = [];

        // Each one of the columns in the update statements needs to be wrapped in the
        // keyword identifiers, also a place-holder needs to be created for each of
        // the values in the list of bindings so we can make the sets statements.
        foreach ($values as $key => $value) {
            $columns[] = $this->wrap($key).' = '.$this->parameter($value);
        }

        $columns = implode(', ', $columns);

        // If the query has any "join" clauses, we will setup the joins on the builder
        // and compile them so we can attach them to this update, as update queries
        // can get join statements to attach to other tables when they're needed.
        if (isset($query->joins)) {
            $joins = ' '.$this->compileJoins($query, $query->joins);
        } else {
            $joins = '';
        }

        // Of course, update queries may also be constrained by where clauses so we'll
        // need to compile the where clauses and attach it to the query so only the
        // intended records are updated by the SQL statements we generate to run.
        $where = $this->compileWheres($query);

        $sql = rtrim("update {$table}{$joins} set $columns $where");

        if (! empty($query->orders)) {
            $sql .= ' '.$this->compileOrders($query, $query->orders);
        }

        if (isset($query->limit)) {
            $sql .= ' '.$this->compileLimit($query, $query->limit);
        }

        return rtrim($sql);
    }

    /**
     * Prepare the bindings for an update statement.
     *
     * @param  array  $bindings
     * @param  array  $values
     * @return array
     */
    public function prepareBindingsForUpdate(array $bindings, array $values)
    {
        return parent::prepareBindingsForUpdate($bindings, $values);
    }

    /**
     * Compile a delete statement into SQL.
     *
     * @param  \Globalis\PuppetSkilled\Database\Query\Builder  $query
     * @return string
     */
    public function compileDelete(Builder $query)
    {
        $table = $this->wrapTable($query->from);

        $where = is_array($query->wheres) ? $this->compileWheres($query) : '';

        if (isset($query->joins)) {
            $joins = ' '.$this->compileJoins($query, $query->joins);

            $sql = trim("delete $table from {$table}{$joins} $where");
        } else {
            $sql = trim("delete from $table $where");

            if (! empty($query->orders)) {
                $sql .= ' '.$this->compileOrders($query, $query->orders);
            }

            if (isset($query->limit)) {
                $sql .= ' '.$this->compileLimit($query, $query->limit);
            }
        }

        return $sql;
    }

    /**
     * Wrap a single string in keyword identifiers.
     *
     * @param  string  $value
     * @return string
     */
    protected function wrapValue($value)
    {
        if ($value === '*') {
            return $value;
        }
        return '`'.str_replace('`', '``', $value).'`';
    }
}
