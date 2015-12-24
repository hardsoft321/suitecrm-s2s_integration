Компоненты для интеграции между двумя SugarCRM

Создает таблицы для записи изменений и отслеживания изменений в других сборках.
Чтобы записывать изменения в таблицу, нужно добавить хуки для нужных модулей
    array(
        'module' => '<module>', //указать модуль
        'hook' => 'after_save',
        'order' => 50,
        'description' => 'Insert into s2s_modifications_log create/update action',
        'file' => 'custom/include/S2S_Integration/S2S_Hooks.php',
        'class' => 'S2S_Hooks',
        'function' => 'afterSave',
    ),
    array(
        'module' => '<module>', //указать модуль
        'hook' => 'after_delete',
        'order' => 50,
        'description' => 'Insert into s2s_modifications_log delete action',
        'file' => 'custom/include/S2S_Integration/S2S_Hooks.php',
        'class' => 'S2S_Hooks',
        'function' => 'afterDelete',
    ),

Чтобы загружать изменения из других сборок, настроить в планировщике задание
"Запуск синхронизации с другими сборками SugarCRM".
Добавить в config.php настройки подключения к внешней базе, например:
    'integration_instances' => array(
        'personal_cabinet' => array(
            'type' => 'sugar2sugar',
            'dbconfig' =>   array (
                'db_host_name' => ...
                ...
            ),
            'dbconfigoption' => ... //не обязательно
            'portion_limit' => 1000,
            'modules' => array(
                'Cases',
                ...
            ),
        ),
    ),
Настроить $sugar_config['cron']['min_cron_interval'] и $sugar_config['jobs']['min_retry_interval'].
Для указанных модулей создать файл [custom/]modules/<module>/s2s_integration.php
с классом [Custom_]<module>_S2S_Integration. В нем реализовать нужную логику.
