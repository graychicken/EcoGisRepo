<?php

/* ------------------------------ PHP configuration assignment ------------------------------ */

$smarty->assign('config', array('precision' => ini_get('precision'),
    'safe_mode' => ini_get('precision'),
    'max_execution_time ' => ini_get('max_execution_time '),
    'max_input_time' => ini_get('max_input_time'),
    'memory_limit' => ini_get('memory_limit'),
    'error_reporting' => ini_get('error_reporting'),
    'display_errors' => ini_get('display_errors'),
    'post_max_size' => ini_get('post_max_size'),
    'default_mimetype' => ini_get('default_mimetype'),
    'default_charset' => ini_get('default_charset'),
    'file_uploads' => ini_get('file_uploads'),
    'upload_max_filesize' => ini_get('upload_max_filesize')));

/* ------------------------------ CONSTANT ------------------------------ */

$smarty->assign('LANG_NAME_SHORT_FMT_1', getLangNameShort(1));
$smarty->assign('LANG_NAME_SHORT_FMT_2', getLangNameShort(2));
$smarty->assign('LANG_NAME_SHORT_FMT_3', getLangNameShort(3));
$smarty->assign('LANG_NAME_1', getLangName(1, true));
$smarty->assign('LANG_NAME_2', getLangName(2, true));
$smarty->assign('LANG_NAME_3', getLangName(3, true));

$smarty->assign('R3_CSS_URL', R3_CSS_URL);
$smarty->assign('R3_JS_URL', R3_JS_URL);
$smarty->assign('R3_ICONS_URL', R3_ICONS_URL);


/* ------------------------------ Permission & DB settings assignment ------------------------------ */

if (isset($auth) && ($auth->isAuth() ||
        $auth->getStatus() == AUTH_PASSWORD_EXPIRED ||
        $auth->getStatus() == AUTH_PASSWORD_REPLACE)) {
    // Some user information
    $smarty->assign('USER_ID', $auth->getUID());
    $smarty->assign('USER_MUNICIPALITY', $auth->getParam('mu_id'));
    $smarty->assign('USER_LOGIN', $auth->getLogin());
    $smarty->assign('USER_NAME', $auth->getParam('us_name'));
    if ($auth->getParam('mu_id') <> '') {
        $smarty->assign('USER_MUNICIPALITY', R3EcoGisHelper::getMunicipalityName($auth->getParam('mu_id')));
    }
    $smarty->assign('USER_IP', $auth->getParam('us_last_ip'));
    $smarty->assign('DOMAIN_NAME', $auth->getDomainName());
    $smarty->assign('APPLICATION_CODE', $auth->getParam('app_code'));
    $smarty->assign('APPLICATION_NAME', $auth->getParam('app_name'));
    $numLanguages = $auth->getConfigValue('APPLICATION', 'NUM_LANGUAGES');

    /** Permission */
    foreach ($auth->getAllPermsAsString() as $_value) {
        $smarty->assign($_value, true);
    }


    /** DB Configuration */
    foreach ($auth->getAllConfigValuesAsString() as $_key => $_value) {
        $smarty->assign($_key, $_value);
    }

    if ($auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getDomainID() <> $_SESSION['do_id']) {
        // Load the configuration for the new municipality
        $auth2 = clone $auth;
        $auth2->loadConfigFor($auth2->getDomainCodeFromID($_SESSION['do_id']), APPLICATION_CODE);
        $numLanguages = $auth2->getConfigValue('APPLICATION', 'NUM_LANGUAGES');
        /** DB Configuration */
        foreach ($auth2->getAllConfigValuesAsString() as $_key => $_value) {
            $smarty->assign($_key, $_value);
        }
    }
    $smarty->assign('NUM_LANGUAGES', $numLanguages);
    unset($_key);
    unset($_value);
} else if (isset($dbini)) {
    $smarty->assign('DOMAIN_NAME', $auth->getDomainName());
    foreach ($dbini->getAllValues() as $_key1 => $_value1) {
        foreach ($_value1 as $_key2 => $_value2) {
            $smarty->assign($_key1 . '_' . $_key2, $_value2);
        }
    }
}
