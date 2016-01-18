<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 * @package s2s_integration
 */
require_once 'custom/include/S2S_Integration/S2S_DBManagerFactory.php';

/**
 * Синхронизация с другими сборками SugarCRM.
 * Текущий экземпляр не вставляет данные во внешние базы, но считывает их и вставляет
 * в свой базу.
 * Настройки подключений к внешним базам настраиваются в $sugar_config['integration_instances'].
 * Для каждого подключения настраивается:
 *   type - для данного класса он должен быть sugar2sugar
 *   name - имя инстанса для сохранения типа внешнего источника
 *   portion_limit - органичение на количество записей, выгружаемых за раз
 *   modules - список модулей, для которых определен класс с функцией интеграции
 */
class S2S_Integration
{
    /**
     * То override in subclasses
     */
    protected $fields_map = array('*'=>'*');
    protected $integrateEmailAddress = false;

    public $module;
    public $instanceName;
    public $extDb;

    public function __construct($instanceName)
    {
        $this->instanceName = $instanceName;
    }

    public function getFieldsMap()
    {
        return $this->fields_map;
    }

    public function isIntegrateEmailAddress()
    {
        return $this->integrateEmailAddress;
    }

    /**
     * Запускает синхронизацию.
     */
    public static function runIntegration()
    {
        $totalResult = true;
        global $sugar_config, $db;
        foreach($sugar_config['integration_instances'] as $instanceName => $config) {
            if($config['type'] != 'sugar2sugar') {
                continue;
            }

            if(!empty($config['modules'])) {
                foreach($config['modules'] as $module) {
                    $sql = "SELECT position FROM s2s_master_position WHERE instance = '{$instanceName}' AND module_name = '{$module}'";
                    $dbRes = $db->query($sql);
                    if(!$dbRes) {
                        return false;
                    }
                    $row = $db->fetchByAssoc($dbRes);
                    $prevPosition = $row ? $row['position'] : null;

                    $maxPosition = 0;
                    $extDb = S2S_DBManagerFactory::getInstance($instanceName);
                    $sql = "SELECT max(serial_num) AS max_serial_num FROM s2s_modifications_log";
                    $dbRes = $extDb->query($sql);
                    if(!$dbRes) {
                        return false;
                    }
                    $row = $extDb->fetchByAssoc($dbRes);
                    $maxPosition = $row && !empty($row['max_serial_num']) ? $row['max_serial_num'] : 0;

                    if(($prevPosition !== null && $prevPosition == $maxPosition) || $maxPosition == 0) {
                        continue;
                    }
                    if(!empty($config['portion_limit'])) {
                        if($maxPosition - (int)$prevPosition > $config['portion_limit']) {
                            $maxPosition = (int)$prevPosition + $config['portion_limit'];
                        }
                    }

                    $result = true;

                    $integration = self::getModuleIntegrationObject($instanceName, $module);
                    try {
                        $result = $integration->runIntegrationPortion($prevPosition, $maxPosition) && $result;
                    }
                    catch(Exception $ex) {
                        $result = false;
                        $GLOBALS['log']->fatal("S2S_Integration: ".$ex->getMessage());
                    }

                    if($result) {
                        $sql = $prevPosition === null
                            ? "INSERT INTO s2s_master_position (instance, module_name, position) VALUES ('{$instanceName}', '{$module}', ".(int)$maxPosition.")"
                            : "UPDATE s2s_master_position SET position = ".(int)$maxPosition." WHERE instance = '{$instanceName}' AND module_name = '{$module}'";
                        $db->query($sql);
                    }
                    $totalResult = $totalResult && $result;
                }
            }
        }
        return $totalResult;
    }

    public static function clearOldLog()
    {
        global $db;
        $db->query("DELETE FROM s2s_modifications_log WHERE date_entered < SYSDATE() - INTERVAL 10 DAY");
    }

    public static function getModuleIntegrationObject($instanceName, $module)
    {
        $integrationClass = self::getModuleIntegrationClass($module);
        $integration = new $integrationClass($instanceName);
        register_shutdown_function('S2S_DBManagerFactory::disconnectAll');
        $extDb = S2S_DBManagerFactory::getInstance($instanceName);
        $integration->extDb = $extDb;
        if(empty($integration->module)) {
            $integration->module = $module;
        }
        return $integration;
    }

    public static function getModuleIntegrationClass($module)
    {
        if(file_exists("custom/modules/{$module}/s2s_integration.php")) {
            require_once "custom/modules/{$module}/s2s_integration.php";
        }
        else {
            require_once "modules/{$module}/s2s_integration.php";
        }
        $customClassName = "Custom_{$module}_S2S_Integration";
        $className = "{$module}_S2S_Integration";
        return class_exists($customClassName, false) ? $customClassName : $className;
    }

    public function runIntegrationPortion($prevPosition, $maxPosition)
    {
        $records = $this->getChangedRecords($prevPosition, $maxPosition);
        return $this->runRecordsIntegration($records);
    }

    public function runRecordsIntegration($records)
    {
        foreach($records as $extId => $changes) {
            $extRow = $this->retrieveExtRow($extId);
            if($extRow !== false) {
                $ownBean = $this->retrieveBeanByExternalId($extId, $extRow);
                if($ownBean) {
                    $ownBean->notify_on_save = false;
                    $this->integrateRowToBean($extRow, $ownBean);
                }
            }
        }
        return true;
    }

    public function getChangedRecords($prevPosition, $maxPosition)
    {
        $sql = $this->getLogPortionQuery($prevPosition, $maxPosition);
        $dbRes = $this->extDb->query($sql);
        if(!$dbRes) {
            throw new Exception("getLogPortionQuery failed");
        }
        $records = array();
        while($row = $this->extDb->fetchByAssoc($dbRes)) {
            if(empty($records[$row['record_id']]['first_action'])) {
                $records[$row['record_id']]['first_action'] = $row['action_type'];
            }
            $records[$row['record_id']]['last_action'] = $row['action_type'];
        }
        return $records;
    }

    public function getLogPortionQuery($prevPosition, $maxPosition)
    {
        $sql = "SELECT * FROM s2s_modifications_log WHERE 1=1";
        if(!empty($this->module)) {
            $sql .= " AND module_name = '{$this->module}'";
        }
        if($prevPosition !== null) {
            $sql .= " AND serial_num > ".(int)$prevPosition;
        }
        if($maxPosition) {
            $sql .= " AND serial_num <= ".(int)$maxPosition;
        }
        $sql .= " ORDER BY serial_num";
        return $sql;
    }

    /**
     * Получает запись из внешней базы.
     * Если вернется false, синхронизация не будет выполнена.
     * Если вернется null, будет синхронизация удаления.
     * Если вернется массив, будет синхронизация создания/изменения.
     */
    public function retrieveExtRow($extId)
    {
        static $table_name;
        if(!$table_name) {
            $table_name = BeanFactory::newBean($this->module)->table_name;
        }
        $sql = "SELECT ".implode(', ', array_keys($this->getFieldsMap()))
            ." FROM {$table_name} WHERE id = '{$extId}'";
        $dbRes = $this->extDb->limitQuery($sql, 0, 1);
        if(!$dbRes) {
            throw new Exception("Row retrieving failed");
        }
        $extRow = $this->extDb->fetchByAssoc($dbRes);
        if(!$extRow) {
            return null;
        }

        if($this->isIntegrateEmailAddress()) {
            $emailAddress = new SugarEmailAddress();
            $emailAddress->db = $this->extDb;
            $extRow['emailAddress'] = $emailAddress->getAddressesByGUID($extId, $this->module);
        }

        return $extRow;
    }

    /**
     * Внимание! Удаленный бин (deleted=1) тоже возвращается.
     * Если возвращается null, синхронизация не будет запущена.
     * Реализация по умолчанию связывает записи по id, что может быть небезопасно,
     * если create_guid сгенерирует одинаковые id. instanceName не учитывается.
     */
    public function retrieveBeanByExternalId($extId, $extRow = null)
    {
        $new = BeanFactory::newBean($this->module);
        $retrieved = $new->retrieve($extId, true, false);
        if($retrieved) {
            $bean = $retrieved;
        }
        else {
            $new->id = $extId;
            $new->new_with_id = true;
            $bean = $new;
        }
        $bean->s2s_integration = true;
        return $bean;
    }

    public function retrieveBeanByStringFields($fields)
    {
        $new = BeanFactory::newBean($this->module);
        $retrieved = $new->retrieve_by_string_fields($fields, true, false);
        if($retrieved) {
            $bean = $retrieved;
        }
        else {
            foreach($fields as $name => $value) {
                $new->$name = $value;
            }
            $bean = $new;
        }
        $bean->s2s_integration = true;
        return $bean;
    }

    public function integrateRowToBean($extRow, $ownBean)
    {
        $ownBean->s2s_instance_name = $this->instanceName;
        if(!$extRow || !empty($extRow['deleted'])) {
            if(!empty($ownBean->fetched_row['id']) && empty($ownBean->fetched_row['deleted'])) {
                $this->markBeanDeleted($ownBean);
            }
        }
        else if($extRow) {
            if(empty($ownBean->fetched_row['deleted'])) {
                $this->saveRowToBean($extRow, $ownBean);
            }
        }
    }

    public function saveRowToBean($extRow, $ownBean)
    {
        if($this->getFieldsMap() == array('*'=>'*')) {
            foreach($extRow as $name => $value) {
                $ownBean->$name = $value;
            }
        }
        else {
            foreach($this->getFieldsMap() as $extName => $ownName) {
                if(!empty($ownName)) {
                    $ownBean->$ownName = $extRow[$extName];
                }
            }
        }
        if(isset($extRow['emailAddress'])) {
            $ownBean->emailAddress = new SugarEmailAddress();
            $ownBean->emailAddress->addresses = $extRow['emailAddress'];
            $_REQUEST['useEmailWidget'] = true; //disable clearing in SugarEmailAddress::handleLegacySave
        }
        return $ownBean->save(!empty($ownBean->notify_on_save));
    }

    public function markBeanDeleted($ownBean)
    {
        $ownBean->mark_deleted($ownBean->id);
    }

    public function diffBeans($bean1, $bean2)
    {
        global $current_language;
        $fields = array_filter(array_values($this->getFieldsMap()));
        $mod_strings = return_module_language($current_language, $bean1->module_name);
        $diff = array();
        $bean1DisplayValues = $bean1->get_list_view_data();
        $bean2DisplayValues = $bean2->get_list_view_data();
        $links = array();
        foreach($bean1->field_defs as $def) {
            if(!empty($def['id_name'])) {
                $links[$def['id_name']] = $def['name'];
            }
        }
        foreach($fields as $field) {
            $vname = $bean1->field_defs[$field]['vname'];
            $label = isset($mod_strings[$vname]) ? $mod_strings[$vname] : $vname;
            if(isset($links[$field])) {
                $bean1DisplayValue = $bean1->{$links[$field]};
                $bean2DisplayValue = $bean2->{$links[$field]};
            }
            else {
                $fieldUpper = strtoupper($field);
                $bean1DisplayValue = $bean1DisplayValues[$fieldUpper];
                $bean2DisplayValue = $bean2DisplayValues[$fieldUpper];
            }
            $diff[$field] = array(
                'name' => $field,
                'label' => $label,
                'bean1Value' => $bean1->$field,
                'bean1DisplayValue' => $bean1DisplayValue,
                'bean2Value' => $bean2->$field,
                'bean2DisplayValue' => $bean2DisplayValue,
                'matched' => strcmp(mb_strtoupper(trim((string)$bean1->$field)), mb_strtoupper(trim((string)$bean2->$field))) === 0,
            );
        }
        //TODO: emailAddress
        return $diff;
    }
}
