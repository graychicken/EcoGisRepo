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
require_once R3_APP_ROOT . 'lib/r3auth_impexp.php';
require_once R3_APP_ROOT . 'lib/simpletable.php';
require_once R3_APP_ROOT . 'lib/storevar.php';
require_once R3_APP_ROOT . 'lib/xajax.php';
require_once R3_APP_ROOT . 'lang/lang.php';

/** Authentication and permission check */
$db = ezcDbInstance::get();
$auth = R3AuthInstance::get();
if (is_null($auth)) {
    $auth = new R3AuthManagerImpExp($db, $auth_options, APPLICATION_CODE);
    R3AuthInstance::set($auth);
}

if (!$auth->isAuth()) {
    Header("location: logout.php?status=" . $auth->getStatusText());
	die();
}

$what = initVar('what');

if ($what == 'CONFIG') {
    if (!$auth->hasPerm('EXPORT', 'CONFIG')) {
        die("PERMISSION DENIED\n");
    }
    $dn_name =        pageVar('fltdn_name',  null, false, false, 'general');
    $app_code =       pageVar('fltapp_code',  null, false, false, 'general');
    $us_login =       pageVar('fltus_login',  null, false, false, 'general');
    $section =        initVar('expsection');
    $mode =           strtoupper(initVar('mode', 'ADD'));
    
    if ($us_login <> '') {
        list($dummy, $us_login) = explode('|', $us_login);
    }

    $data = $auth->exportConfiguration($dn_name, $app_code, $us_login, $section, $mode);
    $fileName = 'CONFIG';
    if ($dn_name != '') {
        $fileName .= '_' . $dn_name;
    }
    if ($app_code != '') {
        $fileName .= '_' . $app_code;
    }
    if ($us_login != '') {
        $fileName .= '_' . $us_login;
    }
    if ($section != '') {
        $fileName .= '_' . $section;
    }
} else if ($what == 'ACNAME') {
    if (!$auth->hasPerm('EXPORT', 'ACNAME')) {
        die("PERMISSION DENIED\n");
    }
    $app_code =       pageVar('fltapp_code',  null, false, false, 'general');
    $ac_verb =        pageVar('fltac_verb',   null, false, false, 'general');
    $ac_name =        pageVar('fltac_name',  null, false, false, 'general');
    $ac_type =        pageVar('fltac_type',  null, false, false, 'general');
    $mode = isset($_REQUEST['mode']) ? strtoupper($_REQUEST['mode']) : 'ADD'; 
    
    $data = $auth->exportACName($app_code, $ac_verb, $ac_name, $ac_type);
    $fileName = 'ACNAME';
    if ($app_code != '') {
        $fileName .= '_' . $app_code;
    }
    if ($ac_verb != '') {
        $fileName .= '_' . $ac_verb;
    }
    if ($ac_name != '') {
        $fileName .= '_' . $ac_name;
    }
    if ($ac_type != '') {
        $fileName .= '_' . $ac_type;
    }
} else if ($what == 'GROUP') {
    if (!$auth->hasPerm('EXPORT', 'GROUP')) {
        die("PERMISSION DENIED\n");
    }
    $app_code =       pageVar('fltapp_code',  null, false, false, 'general');
    $expgroup =       initVar('expgroup');
    if ($expgroup <> '') {
        list($app_code, $expgroup) = explode('|', $expgroup);
    }
    $data = $auth->exportGroup($app_code == '' ? null : $app_code, 
                               $expgroup == '' ? null : $expgroup, 
                               $auth->hasPerm('EXPORT', 'ACNAME'));
    $fileName = 'GROUP';
    if ($app_code != '') {
        $fileName .= '_' . $app_code;
    }
    if ($expgroup != '') {
        $fileName .= '_' . $expgroup;
    }
}

//Begin writing headers
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Cache-Control: public"); 
    
//Use the switch-generated Content-Type
header("Content-Type: application/force-download; charset=UTF-8");
header("Content-Type: text/plain");

//Force the download
header("Content-Disposition: attachment; filename=$fileName.xml");
header("Content-Transfer-Encoding: binary");
header("Content-Length: " . strlen($data));

echo $data;  
