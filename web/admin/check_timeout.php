<?php

$autoinit = false;
require_once dirname(__FILE__) . '/../../etc/config.php';
require_once R3_LIB_DIR . 'eco_app.php';

R3AppInit('admin', array('auth' => true, 'auth_manager' => false));
if ($auth->isAuth()) {
    echo json_encode(array('ok' => 'ok', 'message' => $auth->getConfigValue('APPLICATION', 'MESSAGE', '')));
} else {
    echo json_encode(array('expired' => true));
}
