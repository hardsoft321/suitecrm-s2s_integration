<?php
$manifest = array(
    'name' => 's2s_integration',
    'acceptable_sugar_versions' => array(),
    'acceptable_sugar_flavors' => array('CE'),
    'author' => 'hardsoft321',
    'description' => 'Компоненты для интеграции между двумя SugarCRM',
    'is_uninstallable' => true,
    'published_date' => '2015-12-16',
    'type' => 'module',
    'version' => '1.0.5',
);
$installdefs = array(
    'id' => 's2s_integration',
    'copy' => array(
        array(
            'from' => '<basepath>/source/copy',
            'to' => '.'
        ),
    ),
    'language' => array(
        array (
            'from' => '<basepath>/source/language/Schedulers/ru_ru.s2s_integration.php',
            'to_module' => 'Schedulers',
            'language' => 'ru_ru',
        ),
        array (
            'from' => '<basepath>/source/language/Schedulers/en_us.s2s_integration.php',
            'to_module' => 'Schedulers',
            'language' => 'en_us',
        ),
        array (
            'from' => '<basepath>/source/language/Schedulers/es_es.s2s_integration.php',
            'to_module' => 'Schedulers',
            'language' => 'es_es',
        ),
    ),
    'relationships' => array(
        array(
            'meta_data' => '<basepath>/source/relationships/s2s_integration.php',
        ),
    ),
    'scheduledefs' => array(
        array(
            'from' => '<basepath>/source/scheduledefs/s2s_integration.php',
        ),
    ),
);
