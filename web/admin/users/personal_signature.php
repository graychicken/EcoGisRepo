<?php  /* UTF-8 FILE: òàèü */
$isUserManager = true;

require_once '../../../etc/config.php';
if (file_exists(R3_APP_ROOT . 'lib/r3_auth_gui_start.php')) {
    require_once R3_APP_ROOT . 'lib/r3_auth_gui_start.php';
}
require_once R3_LIB_DIR . 'r3auth_manager.php';
require_once R3_APP_ROOT . 'lib/default.um.php';
require_once R3_LIB_DIR . 'xajax.php';
require_once R3_LIB_DIR . 'storevar.php';
require_once R3_LIB_DIR . 'config_interpreter.php';
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
} else if (!$auth->hasPerm('ADD', 'SIGNATURE')) {
    die("PERMISSION DENIED\n");
}

/** Ajax request */
header('ETag: ' . date('YmdHis') . md5(microtime(true) + rand(0, 65535)));
header('Last-Modified: '.gmdate('D, d M Y H:i:s') . ' GMT'); 
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT'); 
header('Cache-Control: no-store, no-cache, must-revalidate');     // HTTP/1.1 
header('Cache-Control: pre-check=0, post-check=0, max-age=0', false);    // HTTP/1.1 
header('Cache-Control: max-age=0, s-maxage=0, proxy-revalidate', false);
header('Pragma: no-cache'); 

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

// TODO: Add this query to library
$sql = "SELECT count(*) FROM auth.users WHERE us_id=".$auth->getUID()." AND us_signature IS NOT NULL ";
$result = $db->query($sql);
$vlu = $result->fetch();
$smarty->assign('showCurrentSignature', (boolean)$vlu[0]);


$smarty->display('users/personal_signature.tpl');
