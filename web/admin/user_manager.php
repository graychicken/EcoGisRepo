<?php


require_once dirname(__FILE__) . '/../../etc/config.php';
require_once R3_LIB_DIR . 'eco_app.php';
require_once R3_LIB_DIR . 'eco_utils.php';
require_once R3_LIB_DIR . 'storevar.php';
require_once R3_LIB_DIR . 'simpletable.php';
require_once R3_LANG_DIR . 'lang.php';

/* ---------------- Startup ------------------------------------------------- */

R3AppStart('admin', array('auth' => true, 'auth_manager' => false, 'allow_change_password' => true));

/* ---------------- Initial settings ---------------------------------------- */

$status = initVar('status');
$obj = initVar('obj');


/* ------------------------------ Init Settings ------------------------------ */

if ($obj == 'personal_settings' || $auth->passwordStatus < 0) {
    $MenuItem = 'personal_settings';
} else {
    $MenuItem = 'user_manager';
}
$framesetReload = true;
require_once R3_APP_ROOT . 'web/admin/smarty_assign.php';
require_once R3_APP_ROOT . 'web/admin/menu.php';


/* ------------------------------ Output ------------------------------ */

$totMenuItem = 0;
if (($auth->hasPerm('SHOW', 'APPLICATION') || $auth->hasPerm('SHOW', 'ALL_APPLICATIONS')) &&
        $auth->getConfigValue('USER_MANAGER', 'SHOW_CONFIG_MENU') != 'F') {
    $totMenuItem++;
}
if (($auth->hasPerm('SHOW', 'DOMAIN') || $auth->hasPerm('SHOW', 'ALL_DOMAINS')) &&
        $auth->getConfigValue('USER_MANAGER', 'SHOW_DOMAIN_MENU') != 'F') {
    $totMenuItem++;
}
if (($auth->hasPerm('SHOW', 'ACNAME') || $auth->hasPerm('SHOW', 'ALL_ACNAMES')) &&
        $auth->getConfigValue('USER_MANAGER', 'SHOW_ACNAME_MENU') != 'F') {
    $totMenuItem++;
}
if (($auth->hasPerm('SHOW', 'GROUP') || $auth->hasPerm('SHOW', 'ALL_GROUPS')) &&
        $auth->getConfigValue('USER_MANAGER', 'SHOW_GROUP_MENU') != 'F') {
    $totMenuItem++;
}
if (($auth->hasPerm('SHOW', 'USER') || $auth->hasPerm('SHOW', 'ALL_USERS')) &&
        $auth->getConfigValue('USER_MANAGER', 'SHOW_USER_MENU') != 'F') {
    $totMenuItem++;
}
if (($auth->hasPerm('SHOW', 'CONFIG')) &&
        $auth->getConfigValue('USER_MANAGER', 'SHOW_CONFIG_MENU') != 'F') {
    $totMenuItem++;
}
if (($auth->hasPerm('IMPORT', 'CONFIG') || $auth->hasPerm('IMPORT', 'ACNAME')) &&
        $auth->getConfigValue('USER_MANAGER', 'SHOW_IMPORT_MENU') != 'F') {
    $totMenuItem++;
}

$smarty->assign('status', $status);
$smarty->assign('obj', $obj);
$smarty->assign('hasMenu', $totMenuItem > 1);
$smarty->assign('exclude_main_menu', true);

$smarty->assign('padding', '0px');
$smarty->display('user_manager.tpl');

