<?php
namespace Globalis\PuppetSkilled\Settings;

use \Globalis\PuppetSkilled\Database\Query\Builder;

class SettingsDatabase
{
    protected $table;

    protected $keys = ['name'];

    protected $connection;

    protected $fields = [
        'name',
        'value',
        'autoload',
    ];

    public function __construct($connection, $table = 'settings')
    {
        $this->connection = $connection;
        $this->table = $table;
    }

    public function retrieveById($identifier)
    {
        return $this->getQuery()
            ->where('name', $identifier)
            ->first();
    }

    public function getAutoload()
    {
        return $this->getQuery()
            ->where('autoload', 1)
            ->cursor();
    }

    public function update($identifier, $value)
    {
        return $this->getBuilder()
                ->from($this->table)
                ->where('name', $identifier)
                ->update(['value' => $value]);
    }

    public function getQuery()
    {
        $builder =  $this->getBuilder();
        $builder->select($this->fields)
            ->from($this->table);
        return $builder;
    }

    protected function getBuilder()
    {
        return new Builder($this->connection, $this->connection->getQueryGrammar);
    }
}
