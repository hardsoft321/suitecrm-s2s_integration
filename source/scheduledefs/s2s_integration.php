<?php
/**
 * @license http://hardsoft321.org/license/ GPLv3
 * @author Evgeny Pervushin <pea@lab321.ru>
 * @package s2s_integration
 */
$job_strings[] = 's2s_sync_from_master';
$job_strings[] = 's2s_clear_old_log';

function s2s_sync_from_master()
{
    require_once 'custom/include/S2S_Integration/S2S_Integration.php';
    return S2S_Integration::runIntegration();
}

function s2s_clear_old_log()
{
    require_once 'custom/include/S2S_Integration/S2S_Integration.php';
    return S2S_Integration::clearOldLog();
}
