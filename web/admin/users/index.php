<?php
$isUserManager = true;

require_once '../../../etc/config.php';
if (file_exists(R3_APP_ROOT . 'lib/r3_auth_gui_start.php')) {
    require_once R3_APP_ROOT . 'lib/r3_auth_gui_start.php';
}
if (!defined("__R3_AUTH__")){
    require_once R3_APP_ROOT . 'lib/r3auth_manager.php';
}
require_once R3_APP_ROOT . 'lib/storevar.php';

$db = ezcDbInstance::get();
$auth = R3AuthInstance::get();
if (is_null($auth)) {
    $auth = new R3AuthManager($db, $auth_options, APPLICATION_CODE);
    R3AuthInstance::set($auth);
}

$auth->ignoreExpiredPassword = true;  /** ignore expired password to allow user to change it */
if (!$auth->isAuth()) {
    Header("location: logout.php?status=" . $auth->getStatusText());
	die();
}

if ($auth->passwordStatus < 0) {
    /** Password expired */
    $status = AUTH_PASSWORD_EXPIRED;
} else {
    $status = initVar('status');
}

$obj = initVar('obj');

if ($status < 0 || $status == AUTH_PASSWORD_IN_EXPIRATION || $status == AUTH_PASSWORD_REPLACE) {
    $newLocation = 'personal_settings.php?status=' . $status;
} else if ($obj != '') {
    $newLocation = $obj . '.php';
} else {
    if (isset($dbini)) {
        $newLocation = $dbini->getValue('APPLICATION', 'USER_MANAGER_ACCESS_POINT', 'users_list.php');
    } else {
        $newLocation = 'users_list.php';
    } 
}
$newLocation .= strpos($newLocation, '?') === false ? '?' : '&';
$newLocation .= isset($_REQUEST['st']) ? 'st=' . $_REQUEST['st'] . '&' : '';
$newLocation .= isset($_REQUEST['pg']) ? 'pg=' . $_REQUEST['pg'] . '&' : '';
header("location: $newLocation");
die();
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
<title>R3 GIS - USER MANAGEMENT</title>
<style type="text/css">
  span   { font-family: Verdana, Arial, Helvetica, sans-serif; font-size: 10px; font-weight: bold; }
</style>
<script type="text/javascript">

var location = "<?php echo $newLocation; ?>";

function onPageInit() {
    setTimeout('redirect()', 50);
}

function redirect() {
    document.location = location;
}

window.onload = onPageInit;
</script>
</head>
<body>
<span>Loading...</span>
</body>
</html>