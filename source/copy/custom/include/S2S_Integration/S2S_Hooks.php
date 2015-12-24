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
        global $db;
        $action_type = !empty($bean->deleted) ? 'delete' : (!empty($bean->fetched_row['id']) ? 'update' : 'create');
        $date_entered = TimeDate::getInstance()->getNow()->asDb();
        $sql = "INSERT INTO s2s_modifications_log (record_id, module_name, action_type, date_entered)
        VALUES ('{$bean->id}', '{$bean->module_name}', '{$action_type}', '{$date_entered}')";
        $db->query($sql);
    }

    public function afterDelete($bean, $event, $arguments)
    {
        global $db;
        $action_type = 'delete';
        $date_entered = TimeDate::getInstance()->getNow()->asDb();
        $sql = "INSERT INTO s2s_modifications_log (record_id, module_name, action_type, date_entered)
        VALUES ('{$bean->id}', '{$bean->module_name}', '{$action_type}', '{$date_entered}')";
        $db->query($sql);
    }
}
