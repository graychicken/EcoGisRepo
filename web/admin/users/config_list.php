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
require_once R3_APP_ROOT . 'lib/simpletable.php';
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

/** Restore old variabled */
$fltdn_name =        pageVar('fltdn_name',   null, isset($_REQUEST['reset']), false, 'general');
$fltapp_code =       pageVar('fltapp_code',  null, isset($_REQUEST['reset']), false, 'general');
$fltus_login =       pageVar('fltus_login',  null, isset($_REQUEST['reset']), false, 'general');
$fltsection =        pageVar('fltsection',   null, isset($_REQUEST['reset']), false, 'general');

/** filters */
$filter_where = '';

/** Domains list */
$smarty->assign('dn_name_list',  $auth->mkAssociativeArray($auth->getDomainsList(), 'DOMAIN'));
if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && !$auth->hasPerm('SHOW', 'DOMAIN')) {
    $dn_name = $auth->domain;
} else {
    $dn_name = $fltdn_name;
}

/** Applications list */
$smarty->assign('app_code_list', $auth->mkAssociativeArray($auth->getApplicationsList(), 'APPLICATION'));
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

$dbini = new R3DBIni($db, $auth_options);
$dbini->setDomainName($dn_name, true);
$dbini->setApplicationCode($app_code, true);
if (($p = strpos($us_login, '|')) !== false) {
    $us_login = substr($us_login, $p + 1);
}
$dbini->setUserLogin($dn_name, $us_login, true);
$dbini->setShowPrivate(true);

if (isset($_REQUEST['save']) && $_REQUEST['save']) {
    if (!$auth->hasPerm('EDIT', 'CONFIG')) {
        die("PERMISSION DENIED\n");
    }
    foreach($_REQUEST as $key => $value) {
        $a = explode('|', $key);
        if (count($a) == 2) {
            $dbini->setValue($a[0], $a[1], $value);
        }
    }
}


$sections = $dbini->getAllSections();
$smarty->assign('sections', $sections);
$smarty->assign('fltsection', $fltsection);

if (in_array($fltsection, $sections)) {
    $attribs = $dbini->getAllAttributes($fltsection, true);

    foreach ($attribs as $key1 => $val1) {
        foreach ($val1 as $key2 => $val2) {
            if ($val2['se_type'] == 'ARRAY') {
                /** substring prevent to show the forst array */
                $attribs[$key1][$key2]['se_value'] = substr(var_export($attribs[$key1][$key2]['se_value'], true), 7, -3);
                $attribs[$key1][$key2]['se_value'] = htmlspecialchars($attribs[$key1][$key2]['se_value']);
            } else if ($val2['se_type'] == 'ENUM') {
                /** substring prevent to show the forst array */
                $enumData = array();
                foreach(explode("\n", $val2['se_type_ext']) as $enumStr) {
                    $enumExp = explode('=', $enumStr);
                    if (count($enumExp) == 2) {
                        $enumData[trim($enumExp[1])] = trim($enumExp[0]);
                    } else {
                        $enumData[] = 'Error for field ' . $enumExp[0];
                    }
                }
                $attribs[$key1][$key2]['se_enum_data'] = $enumData;
            } else {
                $attribs[$key1][$key2]['se_value'] = htmlspecialchars($attribs[$key1][$key2]['se_value']);
            }
            $attribs[$key1][$key2]['se_descr'] = str_replace("'", '_', $attribs[$key1][$key2]['se_descr']);
            $attribs[$key1][$key2]['se_descr'] = str_replace("\r", '', $attribs[$key1][$key2]['se_descr']);
            $attribs[$key1][$key2]['se_descr'] = str_replace("\n", ' ', $attribs[$key1][$key2]['se_descr']);
            $attribs[$key1][$key2]['se_descr'] = str_replace("\n", '', htmlspecialchars($attribs[$key1][$key2]['se_descr'])); 

        }
    }
    $smarty->assign('attribs', $attribs);
}


$smarty->assign('dn_name',  $dn_name);
$smarty->assign('app_code', $app_code);
if ($us_login != '') {
    $us_login = $dn_name . '|' . $us_login;
}
$smarty->assign('us_login', $us_login);

$smarty->assign('show_private', true);

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
if ($auth->hasPerm('SHOW', 'USER')) {
    $smarty->assign('USER_CAN_SHOW_USER', true);
}
if ($auth->hasPerm('SHOW', 'LOCAL_USER')) {
    $smarty->assign('USER_CAN_SHOW_LOCAL_USER', true);
}
if ($auth->hasPerm('EXPORT', 'CONFIG')) {
    $smarty->assign('USER_CAN_EXPORT_CONFIG', true);
}
if ($auth->hasPerm('EDIT', 'CONFIG')) {
    $smarty->assign('USER_CAN_EDIT_CONFIG', true);
}

$smarty->display('users/config_list.tpl');
