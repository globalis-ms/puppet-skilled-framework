<?php
namespace Globalis\PuppetSkilled\Settings;

class Settings extends \Globalis\PuppetSkilled\Service\Base
{
    protected $settingsTable;

    protected $cache = [];

    public function __construct(SettingsDatabase $settingsTable)
    {
        $this->settingsTable = $settingsTable;
        foreach ($this->settingsTable->getAutoload() as $setting) {
            $this->cache[$setting->name] = $setting->value;
        }
    }

    public function get($name)
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }
        $value = null;
        $setting =$this->settingsTable->retrieveById($name);
        if ($setting) {
            $value = $setting->value;
        }
        $this->cache[$name] = $value;
        return $value;
    }

    public function getTable()
    {
        return $this->settingsTable;
    }

    public function update($name, $value)
    {
        $this->settingsTable->update($name, $value);
    }
}
