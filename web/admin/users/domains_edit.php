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

if (!$auth->hasPerm(strtoupper($_REQUEST['act']), 'DOMAIN') &&
    !$auth->hasPerm(strtoupper($_REQUEST['act']), 'ALL_DOMAINS')) {
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
$objAjax->registerExternalFunction('submitForm', 'domains_edit_ajax.php');
$objAjax->processRequests();
$smarty->assign('xajax_js_include', $objAjax->getJavascript(R3_JS_URL));
  
/** Authentications methods */
$authMethods = $auth->getAuthenticationMethods(array('DB', 'POP3', 'LDAP'/*, 'IMAP'*/));
$smarty->assign('do_auth_type_list', $authMethods);

/** Applications list */
try {
    $appList = $auth->getApplicationsList();
} catch (EPermissionDenied $e) {
}
$smarty->assign('applications_list', $auth->mkAssociativeArray($appList, 'APPLICATION'));


if ($_REQUEST['act'] == 'add') {
    $data['do_auth_type'] = 'DB';
    $data['do_auth_data'] = '';
    $data['do_name'] = '';
    $data['do_alias'] = '';
    $data['applications'] = array();
} else {
    $data = $auth->getDomainData($_REQUEST['name'], true);
    $data['do_name'] = $data['names'][0];
    
    /** Alias */
    $a = array();
    for ($i = 1; $i < count($data['names']); $i++)
        $a[] = $data['names'][$i];
    $data['do_alias'] = implode("\n", $a);
    
    /** Applications */
    $a = $data['applications'];
    foreach($a as $key => $val) {
        $data['applications'][$key] = $key;
    }
}

if ($_REQUEST['act'] == 'show') {
    $smarty->assign('view_style', 'class="input_readonly" readonly');
}
  
$smarty->assign('act', $_REQUEST['act']);
$smarty->assign('vlu', $data);

$smarty->display('users/domains_edit.tpl');
