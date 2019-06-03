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
require_once R3_APP_ROOT . 'lib/storevar.php';
require_once R3_APP_ROOT . 'lib/xajax.php';
require_once R3_APP_ROOT . 'lang/lang.php';



/** Authentication and permission check */
$db = ezcDbInstance::get();
$auth = R3AuthInstance::get();
if (is_null($auth)) {
    $auth = new R3AuthManager($db, $auth_options, APPLICATION_CODE);
    R3AuthInstance::set($auth);
}
if (!$auth->isAuth()) {
    Header("location: logout.php?status=" . $auth->getStatusText());
	die();
}

if (!$auth->hasPerm('SHOW', 'CONFIG')) {
	die("PERMISSION DENIED [SHOW/CONFIG]\n");
}

if (file_exists(R3_APP_ROOT . 'lib/custom.um.php')) {
    require_once(R3_APP_ROOT . 'lib/custom.um.php');
    $umDependenciesObj = getUmDependenciesObject();
} else {
    $umDependenciesObj = new R3UmDependenciesDefault();
}
$smarty->assign('umDependencies', $umDependenciesObj->get());


$fltdn_name =        pageVar('fltdn_name',   null, isset($_REQUEST['reset']), false, 'general');
$fltapp_code =       pageVar('fltapp_code',  null, isset($_REQUEST['reset']), false, 'general');
$fltus_login =       pageVar('fltus_login',  null, isset($_REQUEST['reset']), false, 'general');
$fltsection =        pageVar('fltsection',   null, isset($_REQUEST['reset']), false, 'general');

/** Domains list */
$smarty->assign('dn_name_list',  $auth->mkAssociativeArray($auth->getDomainsList(), 'DOMAIN'));
if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && !$auth->hasPerm('SHOW', 'DOMAIN')) {
    $dn_name = $auth->domain;
} else {
    $dn_name = $fltdn_name;
}

/** Applications list */
$smarty->assign('app_code_list', $auth->mkAssociativeArray($auth->getApplicationsList(array('order'=>'app_name')), 'APPLICATION'));
if (!$auth->hasPerm('SHOW', 'ALL_APPLICATIONS') && !$auth->hasPerm('SHOW', 'APPLICATION')) {
    $app_code = $auth->application;
} else {
    $app_code = $fltapp_code;
}

/** Users list */
$smarty->assign('us_login_list', $auth->mkAssociativeArray($auth->getUsersList($dn_name, $app_code), 'USER'));
if (!$auth->hasPerm('SHOW', 'ALL_USERS') && !$auth->hasPerm('SHOW', 'USER')) {
    $us_login = $auth->application;
} else {
    $us_login = $fltus_login;
}
if (($p = strpos($us_login, '|')) !== false) {
    $us_login2 = substr($us_login, $p + 1);
} else {
    $us_login2 = $us_login;
}

if (!isset($includeSmartyAssign) || $includeSmartyAssign === true) {
    require_once R3_WEB_ADMIN_DIR . 'smarty_assign.php';
}

$dbini = new R3DBIni($db, $auth_options);
$dbini->setDomainName($dn_name, true);
$dbini->setApplicationCode($app_code, true);
$dbini->setUserLogin($dn_name, $us_login2, true);

/** Ajax request */
if (defined('R3_USERMANAGER_RELATIVE_LINKS') && R3_USERMANAGER_RELATIVE_LINKS) {
	$url = basename(__FILE__);
	$p = strpos($_SERVER['REQUEST_URI'], '?');
	if ($p > 0) {
		$url .= substr($_SERVER['REQUEST_URI'], $p);
	}
} else {
	$url = R3_DOMAIN_URL . $_SERVER['REQUEST_URI'];
}
$url .= (strpos($url, '?') === false ? '?' : '&') . 'proxytime=' . md5(time());

$objAjax = new xajax($url);
$objAjax->registerExternalFunction('submitForm', 'config_edit_ajax.php');
$objAjax->processRequests();
$smarty->assign('xajax_js_include', $objAjax->getJavascript(R3_JS_URL));
  
/** parameter type */
$smarty->assign('se_type_list', array('STRING'=>'STRING', 
                                      'TEXT'=>'TEXT',
                                      'NUMBER'=>'NUMBER',
                                      'ENUM'=>'ENUM',
                                      'ARRAY'=>'ARRAY',
                                      'JSON'=>'JSON'));

if ($_REQUEST['act'] == 'add') {
    $data['dn_name'] = $dn_name;
    $data['app_code'] = $app_code;
    $data['us_login'] =$us_login;
    $data['se_section'] = $fltsection;
    $data['se_type'] = 'STRING';
    $data['se_private'] = true;
} else if ($_REQUEST['act'] == 'mod') { 

    if (($p = strpos($us_login, '|')) !== false) {
        $us_login_only = substr($us_login, $p + 1);
    } else {
        $us_login_only = $us_login;
    }
    $data = $dbini->getAttribute($_REQUEST['dn_name'], $_REQUEST['app_code'], $us_login_only, 
                                 $_REQUEST['se_section'], $_REQUEST['se_param']);
    
    if ($data['us_login'] != '') {
        $data['us_login'] = $data['dn_name'] . '|' . $data['us_login'];
    }
    if ($data['se_type'] == 'STRING') {
        $data['se_type_ext_STRING'] = $data['se_type_ext'];
    } else if ($data['se_type'] == 'ENUM') {
        $data['se_type_ext_ENUM'] = $data['se_type_ext'];
    } 
    if ($data['se_type'] == 'TEXT' || $data['se_type'] == 'JSON') {
        $data['se_value_TEXT'] = $data['se_value'];
    } else if ($data['se_type'] == 'ARRAY') {
        $data['se_value_TEXT'] = substr(var_export($data['se_value'], true), 7, -3);
    } else {
        $data['se_value_normal'] = $data['se_value'];
    }
}

if ($_REQUEST['act'] == 'show') {
    $smarty->assign('view_style', 'class="input_readonly" readonly');
}
  
/** Smarty extra permission for administrator (who has no permission assigned) */
if ($auth->hasPerm('SHOW', 'ALL_DOMAINS') || $auth->hasPerm('SHOW', 'DOMAIN')) {
    $smarty->assign('USER_CAN_SHOW_DOMAIN', true);
}
if ($auth->hasPerm('SHOW', 'ALL_APPLICATIONS')) {
    $smarty->assign('USER_CAN_SHOW_ALL_APPLICATIONS', true);
}
if ($auth->hasPerm('SHOW', 'APPLICATION')) {
    $smarty->assign('USER_CAN_SHOW_APPLICATION', true);
}

if ($auth->hasPerm('SHOW', 'ALL_USERS')) {
    $smarty->assign('USER_CAN_SHOW_ALL_USERS', true);
}
if ($auth->hasPerm('EXPORT', 'CONFIG')) {
    $smarty->assign('USER_CAN_EXPORT_CONFIG', true);
}
if ($auth->hasPerm('EDIT', 'CONFIG')) {
    $smarty->assign('USER_CAN_EDIT_CONFIG', true);
}

$smarty->assign('act', $_REQUEST['act']);
$smarty->assign('vlu', $data);

$smarty->display('users/config_edit.tpl');
