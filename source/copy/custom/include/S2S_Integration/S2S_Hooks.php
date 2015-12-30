<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 * @package s2s_integration
 */
class S2S_Hooks
{
    public function afterSave($bean, $event)
    {
        $action_type = !empty($bean->deleted) ? 'delete' : (!empty($bean->fetched_row['id']) ? 'update' : 'create');
        self::insertModification($bean->module_name, $bean->id, $action_type);
    }

    public function afterDelete($bean, $event, $arguments)
    {
        self::insertModification($bean->module_name, $bean->id, 'delete');
    }

    public static function insertModification($module_name, $record_id, $action_type = 'update')
    {
        global $db;
        $date_entered = TimeDate::getInstance()->getNow()->asDb();
        $sql = "INSERT INTO s2s_modifications_log (record_id, module_name, action_type, date_entered)
        VALUES ('{$record_id}', '{$module_name}', '{$action_type}', '{$date_entered}')";
        $db->query($sql);
    }
}
