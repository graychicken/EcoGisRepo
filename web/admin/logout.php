<?php

$scriptStartTime = microtime(true);
$autoinit = false;
require_once dirname(__FILE__) . '/../../etc/config.php';
require_once R3_LIB_DIR . 'eco_app.php';
initLog();  // Initialize ezLog
register_shutdown_function('shutdown');

R3AppInit('admin', array('dbini' => true));

/**
 * Logout the user and redirect to the right page (login)
 */
$auth->logout();
$logoutAddress = $dbini->getValue('APPLICATION', 'LOGOUT_ADDRESS', 'login.php');

/* Attach status */
if (isset($_REQUEST['status'])) {
    if (strpos($logoutAddress, '?') === false) {
        $logoutAddress .= '?status=' . $_REQUEST['status'];
    } else {
        $logoutAddress .= '&status=' . $_REQUEST['status'];
    }
}
Header("Location: $logoutAddress");
