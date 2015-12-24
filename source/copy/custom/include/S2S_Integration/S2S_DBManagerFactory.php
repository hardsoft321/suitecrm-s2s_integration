<?php
require_once 'include/database/DBManagerFactory.php';

class S2S_DBManagerFactory extends DBManagerFactory
{
    public static function getInstance($instanceName)
    {
        global $sugar_config;
        static $count = 0, $old_count = 0;

        if(!isset(self::$instances[$instanceName])){
            $config = $sugar_config['integration_instances'][$instanceName]['dbconfig'];
            $count++;
                self::$instances[$instanceName] = self::getTypeInstance($config['db_type'], $config);
                if(isset($sugar_config['integration_instances'][$instanceName]['dbconfigoption'])) {
                    if(!empty($sugar_config['integration_instances'][$instanceName]['dbconfigoption'])) {
                        self::$instances[$instanceName]->setOptions($sugar_config['integration_instances'][$instanceName]['dbconfigoption']);
                    }
                }
                else if(!empty($sugar_config['dbconfigoption'])) {
                    self::$instances[$instanceName]->setOptions($sugar_config['dbconfigoption']);
                }
                self::$instances[$instanceName]->connect($config, true);
                self::$instances[$instanceName]->count_id = $count;
                self::$instances[$instanceName]->references = 0;
        } else {
            $old_count++;
            self::$instances[$instanceName]->references = $old_count;
        }
        return self::$instances[$instanceName];
    }
}
