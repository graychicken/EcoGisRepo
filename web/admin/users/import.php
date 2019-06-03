<?php
$isUserManager = true;

require_once '../../../etc/config.php';
if (file_exists(R3_APP_ROOT . 'lib/r3_auth_gui_start.php')) {
    require_once R3_APP_ROOT . 'lib/r3_auth_gui_start.php';
}
if (!defined("__R3_AUTH__")){
    require_once R3_APP_ROOT . 'lib/r3auth.php';
}
require_once R3_APP_ROOT . 'lib/r3auth_manager.php';
require_once R3_APP_ROOT . 'lib/default.um.php';
require_once R3_APP_ROOT . 'lib/r3auth_impexp.php';
require_once R3_APP_ROOT . 'lib/storevar.php';
require_once R3_APP_ROOT . 'lib/xajax.php';
require_once R3_APP_ROOT . 'lang/lang.php';



/** Authentication and permission check */
$db = ezcDbInstance::get();
$auth = R3AuthInstance::get();
if (is_null($auth)) {
    $auth = new R3AuthManagerImpExp($db, $auth_options, APPLICATION_CODE);
    R3AuthInstance::set($auth);
}

if (!$auth->isAuth()) {
    Header("location: logout.php?status=" . $auth->getStatusText());
	die();
}

if (!$auth->hasPerm('IMPORT', 'CONFIG') && 
    !$auth->hasPerm('IMPORT', 'ACNAME') /*&& */
    /*!$auth->hasPerm('IMPORT', 'GROUP') && */
    /*!$auth->hasPerm('IMPORT', 'USER')*/) {
	die("PERMISSION DENIED\n");
}

if (file_exists(R3_APP_ROOT . 'lib/custom.um.php')) {
    require_once(R3_APP_ROOT . 'lib/custom.um.php');
    $umDependenciesObj = getUmDependenciesObject();
} else {
    $umDependenciesObj = new R3UmDependenciesDefault();
}
$smarty->assign('umDependencies', $umDependenciesObj->get());

if (!isset($includeSmartyAssign) || $includeSmartyAssign === true) {
    require_once R3_WEB_ADMIN_DIR . 'smarty_assign.php';
}

$act = isset($_REQUEST['act']) ? $_REQUEST['act'] : null;
$fltdn_name =        pageVar('fltdn_name',   null, isset($_REQUEST['reset']), false, 'general');
$fltapp_code =       pageVar('fltapp_code',  null, isset($_REQUEST['reset']), false, 'general');

$vlu = array('fltdn_name'=>$fltdn_name, 'fltapp_code'=>$fltapp_code);

if ($act == 'import') {
    if ($auth->hasPerm('SHOW', 'ALL_DOMAINS') || $auth->hasPerm('SHOW', 'DOMAIN')) {
        $dn_name = $fltdn_name;
    } else {
        $dn_name = $auth->domain;
    }
    if ($auth->hasPerm('SHOW', 'ALL_APPLICATIONS') || $auth->hasPerm('SHOW', 'APPLICATION')) {
        $app_code = $fltapp_code;
    } else {
        $app_code = $auth->application;
    }

    $res = $auth->import($dn_name, $app_code, file_get_contents($_FILES['file']['tmp_name']));
    $smarty->assign('import_result', $res);
    //print_r($res);
    
    /*
    if ($_FILES['config']['error'] == 0) {
        if (!$auth->hasPerm('IMPORT', 'CONFIG')) {
            die("PERMISSION DENIED\n");
        }
        $config_res = $auth->importConfiguration($dn_name, $app_code, null, null, file_get_contents($_FILES['config']['tmp_name']));
        $config_res['set_tot'] = count($config_res['set']);
        $config_res['add_tot'] = count($config_res['add']);
        $config_res['ign_tot'] = count($config_res['ign']);
        $smarty->assign('config_res', $config_res);
    }

    if ($_FILES['acname']['error'] == 0) {
        if (!$auth->hasPerm('IMPORT', 'ACNAME')) {
            die("PERMISSION DENIED\n");
        }
        $acname_res = $auth->importACName($app_code, null, null, file_get_contents($_FILES['acname']['tmp_name']));
        $smarty->assign('acname_res', $acname_res);
    }
    */

}

/** Domains list */
try {
    $smarty->assign('dn_name_list', $auth->mkAssociativeArray($auth->getDomainsList(), 'DOMAIN'));
} catch (EPermissionDenied $e) {
    $smarty->assign('dn_name_list', array($auth->domain=>$auth->domain));
}

/** Applications list */
try {
    $smarty->assign('app_code_list', $auth->mkAssociativeArray($auth->getApplicationsList(array('order'=>'app_name')), 'APPLICATION'));
} catch (EPermissionDenied $e) {
    $smarty->assign('app_code_list', array($auth->application=>$auth->application));
}

/** Smarty extra permission for administrator (who has no permission assigned) */
if ($auth->hasPerm('SHOW', 'ALL_APPLICATIONS')) {
    $smarty->assign('USER_CAN_SHOW_ALL_APPLICATIONS', true);
}
if ($auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
    $smarty->assign('USER_CAN_SHOW_ALL_DOMAINS', true);
}
if ($auth->hasPerm('IMPORT', 'CONFIG')) {
    $smarty->assign('USER_CAN_IMPORT_CONFIG', true);
}
if ($auth->hasPerm('IMPORT', 'ACNAME')) {
    $smarty->assign('USER_CAN_IMPORT_ACNAME', true);
}

$smarty->assign('vlu', $vlu);

$smarty->display('users/import.tpl');
