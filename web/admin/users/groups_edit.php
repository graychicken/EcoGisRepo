<?php
$isUserManager = true;

require_once '../../../etc/config.php';
if (file_exists(R3_APP_ROOT . 'lib/r3_auth_gui_start.php')) {
    require_once R3_APP_ROOT . 'lib/r3_auth_gui_start.php';
}
if (!defined("__R3_AUTH__")){
    require_once R3_APP_ROOT . 'lib/r3auth.php';
}
require_once R3_APP_ROOT . 'lib/r3auth_text.php';
require_once R3_APP_ROOT . 'lib/r3auth_manager.php';
require_once R3_APP_ROOT . 'lib/default.um.php';
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

if (!$auth->hasPerm(strtoupper($_REQUEST['act']), 'GROUP') &&
    !$auth->hasPerm(strtoupper($_REQUEST['act']), 'ALL_GROUPS')) {
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
$objAjax->registerExternalFunction('submitForm', 'groups_edit_ajax.php');
$objAjax->registerExternalFunction('load_select', 'groups_edit_ajax.php');
$objAjax->registerExternalFunction('copy_group', 'groups_edit_ajax.php');
$objAjax->registerExternalFunction('append_group', 'groups_edit_ajax.php');
$objAjax->processRequests();
$smarty->assign('xajax_js_include', $objAjax->getJavascript(R3_JS_URL));

/** Applications list */
$app_code_list = array();
try {
    $app_code_list = $auth->mkAssociativeArray($auth->getApplicationsList(array('order'=>'app_name')), 'APPLICATION');
} catch (EPermissionDenied $e) {
    $app_code_list = array($auth->application=>$auth->application);
    $app_code = $auth->application;    
}
if (!$auth->hasPerm('SHOW', 'ALL_APPLICATIONS') && count($app_code_list) <= 1) {
    $app_code = $auth->application;
}
$smarty->assign('app_code_list', $app_code_list);

$dn_name_list = array();
try {
    $dn_name_list = $auth->mkAssociativeArray($auth->getDomainsList(array('order'=>'dn_name')), 'DOMAIN');
} catch (EPermissionDenied $e) {
    $dn_name_list = array($auth->domain=>$auth->domain);
    $dn_name = $auth->domain;    
}
if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && count($dn_name_list) <= 1) {
    $dn_name = $auth->domain;    
}
$smarty->assign('dn_name_list', $dn_name_list);


$acNameList = array();
foreach($auth->getACNamesTypeList() as $key => $val) {
    $val = $authText["user_manager_acname_type_$key"];
    if ($_REQUEST['code'] != '') {
        try {
            $acNameList[$val] = $auth->mkAssociativeArray($auth->getACNamesList($_REQUEST['code'], array('where'=>'ac_type=' . $db->quote($key))), 'ACNAME');
        } catch (EPermissionDenied $e) {
        }  
    }
}
$smarty->assign('privileges_list', $acNameList);


if ($_REQUEST['act'] == 'add') {
    $data['app_code'] = $_REQUEST['code'];
    $data['app_name'] = '';
} else {
    $data = $auth->getGroupData($_REQUEST['code'], $_REQUEST['name'], true);
    foreach($data['perm'] as $key => $val) {
        $s = $val['ac_verb'] . '|' . $val['ac_name'];
        $data['perm'][$s] = $s;
    }
    
}

if ($_REQUEST['act'] == 'show') {
    $smarty->assign('view_style', 'class="input_readonly" readonly');
}


$smarty->assign('act', $_REQUEST['act']);
$smarty->assign('vlu', $data);

$smarty->display('users/groups_edit.tpl');
