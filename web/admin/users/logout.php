<?php
$isUserManager = true;

require_once '../../../etc/config.php';
if (file_exists(R3_APP_ROOT . 'lib/r3_auth_gui_start.php')) {
    require_once R3_APP_ROOT . 'lib/r3_auth_gui_start.php';
}
if (!defined("__R3_AUTH__")){
    require_once R3_APP_ROOT . 'lib/r3auth.php';
}
require_once R3_APP_ROOT . 'lib/storevar.php';

$db = ezcDbInstance::get();
$auth = R3AuthInstance::get();
if (is_null($auth)) {
    $auth = new R3AuthManager($db, $auth_options, APPLICATION_CODE);
    R3AuthInstance::set($auth);
}

if ($auth->status < 0) {
    /** Password expired */
    $status = AUTH_PASSWORD_EXPIRED;
} else {
    $status = initVar('status');
}
Header("location: ../logout.php?status=$status");
die();
?>
<html>
<title>Logout</title>
<head>
<script type="text/javascript" >

window.onload=function() {
    top.document.src = '../logout.php';
};
    
</script>
</head>

<body>

</body>
</html>
