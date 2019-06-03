<?php
$isUserManager = true;

require_once '../../../etc/config.php';
if (file_exists(R3_APP_ROOT . 'lib/r3_auth_gui_start.php')) {
    require_once R3_APP_ROOT . 'lib/r3_auth_gui_start.php';
}
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

if (!$auth->hasPerm(strtoupper($_REQUEST['act']), 'ACNAME') &&
    !$auth->hasPerm(strtoupper($_REQUEST['act']), 'ALL_ACNAMES')) {
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
$objAjax->registerExternalFunction('submitForm', 'acnames_edit_ajax.php');


$objAjax->processRequests();
$smarty->assign('xajax_js_include', $objAjax->getJavascript(R3_JS_URL));

//$smarty->assign('app_code_list', $auth->mkAssociativeArray($auth->getApplicationsList(array('order'=>'app_name')), 'APPLICATION'));

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

/** Type list */
$acTypeList = array();
try {
    $acTypeList = $auth->getACNamesTypeList();
    foreach($acTypeList as $key => $val)
      $acTypeList[$key] = $authText["user_manager_acname_type_$key"];
} catch (EPermissionDenied $e) {
    $acTypeList = null;
}

$smarty->assign('ac_type_list', $acTypeList);

if ($_REQUEST['act'] == 'add') {
    $data['app_code'] = $_REQUEST['app_code'];
    $data['ac_verb'] = '';
    $data['ac_name'] = '';
    $data['ac_descr'] = '';
    $data['ac_order'] = '0';
    $data['ac_active'] = true;
    $data['ac_type'] = 'C';

} else {
    $data = $auth->getACNameData($_REQUEST['app_code'], $_REQUEST['ac_verb'], $_REQUEST['ac_name']);
}

if ($_REQUEST['act'] == 'show') {
    $smarty->assign('view_style', 'class="input_readonly" readonly');
}
  
$smarty->assign('act', $_REQUEST['act']);
$smarty->assign('vlu', $data);

$smarty->display('users/acnames_edit.tpl');
