<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 */
$dictionary['s2s_modifications_log'] = array(
    'table' => 's2s_modifications_log',
    'fields' => array(
        'serial_num' => array(
            'name' =>'serial_num',
            'type' =>'int',
            'auto_increment' => '1',
            'required' => true
        ),
        'record_id' => array(
            'name' => 'record_id',
            'type' => 'id',
        ),
        'module_name' => array(
            'name' => 'module_name',
            'type' => 'varchar',
            'len' => '100',
        ),
        'action_type' => array(
            'name' => 'action_type',
            'type' => 'varchar',
            'len' => '10',
        ),
        'date_entered' => array(
            'name' => 'date_entered',
            'type' => 'datetime',
        ),
    ),
    'indices' => array(
        array('name' =>'s2s_modif_log_pk', 'type' =>'primary', 'fields'=>array('serial_num')),
        array('name' =>'s2s_modif_log_module_idx', 'type' =>'index', 'fields'=>array('module_name')),
    ),
);

$dictionary['s2s_master_position'] = array(
    'table' => 's2s_master_position',
    'fields' => array(
        'instance' => array(
            'name' => 'instance',
            'type' => 'varchar',
            'len' => '32',
            'comment' => 'Db instance name as configured in sugar_config integration_instances',
        ),
        'module_name' => array(
            'name' => 'module_name',
            'type' => 'varchar',
            'len' => '100',
        ),
        'position' => array(
            'name' => 'position',
            'type' => 'int',
            'comment' => 'Last read serial_num in s2s_modifications_log on master instance',
        ),
    ),
    'indices' => array(
        array('name' =>'s2s_master_position_pk', 'type' =>'primary', 'fields'=>array('instance', 'module_name')),
    ),
);
