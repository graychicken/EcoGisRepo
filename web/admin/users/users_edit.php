<?php
$isUserManager = true;

require_once '../../../etc/config.php';
if (file_exists(R3_APP_ROOT . 'lib/r3_auth_gui_start.php')) {
    require_once R3_APP_ROOT . 'lib/r3_auth_gui_start.php';
}
if (!defined("__R3_AUTH__")) {
    require_once R3_APP_ROOT . 'lib/r3auth.php';
}
require_once R3_LIB_DIR . 'r3auth_manager.php';
require_once R3_APP_ROOT . 'lib/default.um.php';
require_once R3_LIB_DIR . 'xajax.php';
if (!defined('R3_UM_JQUERY') || !R3_UM_JQUERY)
    require_once R3_LIB_DIR . 'calendarfunc.php';
require_once R3_LIB_DIR . 'config_interpreter.php';
require_once R3_APP_ROOT . 'lang/lang.php';

/** Authentication and permission check */
$db = ezcDbInstance::get();
$auth = R3AuthInstance::get();
if (is_null($auth)) {
    $auth = new R3AuthManager($db, $auth_options, APPLICATION_CODE);
    R3AuthInstance::set($auth);
}
global $dbini;

if (!$auth->isAuth()) {
    Header("location: logout.php?status=" . $auth->getStatusText());
    die();
}

if (!$auth->hasPerm(strtoupper($_REQUEST['act']), 'ALL_USERS') &&
        !$auth->hasPerm(strtoupper($_REQUEST['act']), 'USER') &&
        !$auth->hasPerm(strtoupper($_REQUEST['act']), 'LOCAL_USER')) {
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

function ISOToDate($date, $separator = '/') {

    $date = trim($date);
    if ($date == '') {
        return '';
    }
    $resArr = explode('-', $date);
    if (count($resArr) == 3) {
        return $resArr[2] . $separator . $resArr[1] . $separator . $resArr[0];
    }
    return null;
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
if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $url = str_replace($_SERVER['HTTP_HOST'], $_SERVER['HTTP_X_FORWARDED_HOST'], $url);
}

$objAjax = new xajax($url);
$objAjax->registerExternalFunction('submitForm', 'users_edit_ajax.php');
$objAjax->processRequests();
$smarty->assign('xajax_js_include', $objAjax->getJavascript(R3_JS_URL));
$appList = array();

if (!$auth->isSuperuser() &&
        ($_REQUEST['act'] != 'show' && $_REQUEST['act'] != 'add' && $auth->compareUserPerm($_REQUEST['dn_name'], null, $_REQUEST['us_login']) < 0)) {
    $smarty->assign('message', (!isset($txt['not_enough_permission']) ? _("Attenzione! L'utente selezionato ha privilegi che tu non hai. Per questo motivo non puoi modificarlo.") : $txt['not_enough_permission']));
    $_REQUEST['act'] = 'show';
    //SS: TODO: Dare la possibilità di cambiare gruppo e permission solo delle applicazioni di cui ho tutti i diritti
    //SS: TODO: Permettere di vedere solo alcune applicazioni e non tutte
}


/** User extra field for the common section */
$extra_fields = $auth->getConfigValue('USER_MANAGER', 'EXTRA_FIELDS', array());
if (isset($users_extra_fields)) {
    $extra_fields = array_merge($extra_fields, $users_extra_fields);
}

if ($_REQUEST['act'] == 'add') {
    /** ADD a new user */
    if ($_REQUEST['dn_name'] == '') {
        $domains = $auth->getDomainsList(array('order' => 'dn_name'));
        if (count($domains) == 1) {
            $_REQUEST['dn_name'] = $domains[0]['dn_name'];
            $data['dn_name'] = $_REQUEST['dn_name'];
        } else {
            $smarty->assign('dn_name_list', $auth->mkAssociativeArray($domains, 'DOMAIN'));
            $_REQUEST['dn_name'] = '';
        }
    } else {
        $data['dn_name'] = $_REQUEST['dn_name'];
    }

    if (isset($_REQUEST['gr_name']) && $_REQUEST['gr_name'] != '') {
        $a = explode('|', $_REQUEST['gr_name']);
        if (count($a) == 1) {
            $gr_name = $a[0];
        } else {
            $gr_name = $a[1];
        }
    } else {
        $gr_name = '';
    }

    if (isset($_REQUEST['app_code']) && $_REQUEST['app_code'] != '') {
        $app_code = $_REQUEST['app_code'];
    } else {
        $app_code = '';
    }

    $data['app_code'] = $app_code;
    $data['gr_name'] = $gr_name;
    $data['us_login'] = '';
    $data['us_start_date'] = '';
    $data['us_expire_date'] = '';
    $data['us_status'] = 'E';
    $ip_list = '';
} else {
    /** mod, del, show */
    $data = $auth->getUserData($_REQUEST['dn_name'], null, $_REQUEST['us_login'], true);

    $data['us_start_date'] = ISOToDate($data['us_start_date']);
    $data['us_expire_date'] = ISOToDate($data['us_expire_date']);

    $ip_list = $auth->arrayIPToString($data['ip']);
}


readFieldArray($db, $auth, $extra_fields, $data, array('ignoreReadOnly' => true, 'ignoreHidden' => true));

$domainData = $auth->getDomainData($_REQUEST['dn_name'], true);
$appList = $domainData['applications'];

$grp_perm = array();

$groupsList = $auth->getGroupsList();

//print_r($groupsList);
$permList = $auth->getACNamesList(null, array('order' => 'ac_type, ac_order, app_code, ac_verb, ac_name, ac_active'));

if (is_array($appList)) {
    foreach ($appList as $appKey => $appVal) {

        $grp = array();
        $perm = array();
        $perm_n = array();

        $tmpini = new R3DBIni($db, $auth_options, DOMAIN_NAME, $appKey);
        $max_groups = $tmpini->getValue('USER_MANAGER', 'MAX_GROUPS', '');
        $group_mandatory = $tmpini->getValue('USER_MANAGER', 'GROUPS_MANDATORY', '');

        $has_user_perm = $tmpini->getValue('USER_MANAGER', 'HAS_USER_PERM', '');
        $has_user_perm_negate = $tmpini->getValue('USER_MANAGER', 'HAS_USER_PERM_NEGATE', '');
        $default_group = $tmpini->getValue('USER_MANAGER', 'DEFAULT_GROUP', '');

        /** groups */
        if ($max_groups != '0') {
            foreach ($groupsList as $grpVal) {
                // echo "[$appKey]";
                if ($grpVal['app_code'] == $appKey) {

                    // echo $grpVal['app_code'] . $grpVal['gr_name'] . "\n";  
                    //if ($_REQUEST['act'] != 'add' || $auth->hasAllPermsOfGroup($grpVal['app_code'], $grpVal['gr_name'])) {
                    if ($auth->hasPerm('SHOW', 'ALL_GROUPS') == 'T' || $auth->hasAllPermsOfGroup($grpVal['app_code'], $grpVal['gr_name'])) {
                        // echo $grpVal['app_code'], $grpVal['gr_name'] . "<br />\n";
                        if ($_REQUEST['act'] == 'add') {
                            $status = ($grpVal['gr_name'] == $default_group ? 'ON' : 'OFF');
                        } else {
                            $status = ($auth->isMemberOfGroup($data['dn_name'], $data['us_login'], $grpVal['app_code'], $grpVal['gr_name']) ? 'ON' : 'OFF');
                        }
                        $grp[] = array('gr_name' => $grpVal['gr_name'],
                            'status' => $status);
                    }
                }
            }
        }

        /** permissions */
        if ($has_user_perm != 'F') {
            foreach ($permList as $permVal) {
                if ($permVal['app_code'] == $appKey) {
                    $perm[] = array('ac_verb' => $permVal['ac_verb'],
                        'ac_name' => $permVal['ac_name'],
                        'ua_status' => $auth->hasPermForApplication($data['dn_name'], $data['us_login'], $permVal['app_code'], $permVal['ac_verb'], $permVal['ac_name'], 'ALLOW', false, true) ? 'ALLOW' : 'DENY');
                }
            }
        }

        /** permissions */
        if ($has_user_perm_negate != 'F') {
            foreach ($permList as $permVal) {
                if ($permVal['app_code'] == $appKey) {
                    $perm_n[] = array('ac_verb' => $permVal['ac_verb'],
                        'ac_name' => $permVal['ac_name'],
                        'ua_status' => $auth->hasPermForApplication($data['dn_name'], $data['us_login'], $permVal['app_code'], $permVal['ac_verb'], $permVal['ac_name'], 'DENY', false, true) ? 'ALLOW' : 'DENY');
                }
            }
        }

        $grp_perm[$appKey] = array('name' => $appVal,
            'groups' => $grp,
            'perms' => $perm,
            'perms_n' => $perm_n,
            'group_mandatory' => $group_mandatory,
            'max_groups' => $max_groups,
            'has_user_perm' => $has_user_perm,
            'has_user_perm_negate' => $has_user_perm_negate);
    }
}

$auth_settings['options'] = array();
$auth_settings['selected'] = NULL;
$authTypes = array();
if (isset($domainData['auth_settings'])) {
	foreach($domainData['auth_settings'] as $authCode=>$authData) {
		$authTypes[$authCode] = $authData['as_name'];
	}
}

$canChangePassword = $auth->canChangePassword();
$canAddUser = $canChangePassword;
$canModUser = $canChangePassword;
$canDelUser = $canChangePassword;



$smarty->assign('canChangePassword', $canChangePassword && ($auth->getConfigValue('USER_MANAGER', 'CHANGE_USER_PASSWORD') != 'F'));
$smarty->assign('canChangeUserName', $canChangePassword && ($auth->getConfigValue('USER_MANAGER', 'CHANGE_USER_NAME') != 'F'));
$smarty->assign('canChangeLogin', $canChangePassword && ($auth->getConfigValue('USER_MANAGER', 'CHANGE_LOGIN') != 'F'));

//SS: da finire!
$smarty->assign('canAddUser', $canAddUser);
$smarty->assign('canModUser', $canModUser);
$smarty->assign('canDelUser', $canDelUser);

//print_r($grp_perm);
$smarty->assign('grp_perm', $grp_perm);

if ($_REQUEST['act'] == 'show') {
    $smarty->assign('view_style', 'class="input_readonly" readonly');
}

$smarty->assign('extra_fields', $extra_fields);

$smarty->assign('ip_list', $ip_list);

$smarty->assign('act', $_REQUEST['act']);
$smarty->assign('vlu', $data);
$smarty->assign('auth_types', $authTypes);

$WebURL = R3_APP_URL;
if (!defined('R3_UM_JQUERY') || !R3_UM_JQUERY) {
    $smarty->assign('startDate', createDateInput($data['us_start_date'], 'us_start_date', 'us_start_date'));
    $smarty->assign('expireDate', createDateInput($data['us_expire_date'], 'us_expire_date', 'us_expire_date'));
    $smarty->assign('calendarHeader', GetCalendarHeader());
}

$smarty->display('users/users_edit.tpl');
