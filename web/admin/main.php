<?php

$scriptStartTime = microtime(true);
$autoinit = false;
require_once dirname(__FILE__) . '/../../etc/config.php';
require_once R3_LIB_DIR . 'eco_app.php';
register_shutdown_function('shutdown');

/* ---------------- Startup ----------------------------------- */

R3AppInit('admin', array('auth' => true, 'auth_manager' => false));

if (!$auth->isAuth()) {
    Header("location: logout.php?status=" . $auth->getStatusText());
    die();
}

if (!$auth->hasPerm('SHOW', 'BUILDING')) {
    // If no permission go to the user manager (for installation)
    Header("location: app_manager.php?page=user_manager&init");
    die();
}
if ($auth->getConfigValue('APPLICATION', 'MODE') == 'FRAME') {
    Header("location: app_manager.php?on=building&init");
} else {
    Header("location: list.php?on=building&init");
}
