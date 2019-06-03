<?php
$isUserManager = true;

$time_start = microtime(true);

require_once __DIR__ . '/../../../etc/config.php';
if (file_exists(R3_APP_ROOT . 'lib/r3_auth_gui_start.php')) {
    require_once R3_APP_ROOT . 'lib/r3_auth_gui_start.php';
}

if (!defined("__R3_AUTH__")){
    require_once R3_APP_ROOT . 'lib/r3auth.php';
}
require_once R3_LIB_DIR . 'r3auth_manager.php';
require_once R3_LIB_DIR . 'default.um.php';
require_once R3_LIB_DIR . 'simpletable.php';
require_once R3_LIB_DIR . 'storevar.php';
require_once R3_LIB_DIR . 'xajax.php';
require_once R3_LIB_DIR . 'config_interpreter.php';
require_once R3_APP_ROOT . 'lang/lang.php';

$performance_time[] = array('text'=>'Before auth time: ', 'time'=>microtime(true));
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
if (!$auth->hasPerm('SHOW', 'ALL_USERS') &&
    !$auth->hasPerm('SHOW', 'USER') &&
    !$auth->hasPerm('SHOW', 'LOCAL_USER')) {
	die("PERMISSION DENIED\n");
}

$performance_time[] = array('text'=>'After auth time: ', 'time'=>microtime(true));

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
$objAjax->registerExternalFunction('submitForm', 'users_edit_ajax.php');
$objAjax->processRequests();
$smarty->assign('xajax_js_include', $objAjax->getJavascript(R3_JS_URL));

/** Server permission */
$canChangePassword = $auth->canChangePassword();
$canAddUser = $canChangePassword;
$canModUser = $canChangePassword;
$canDelUser = $canChangePassword;


if ($auth->hasPerm('ADD', 'USER')) {
    //SS: Serve per superadmin
    $smarty->assign('USER_CAN_ADD_USER', true);
}

if ($auth->hasPerm('ADD', 'ALL_USERS')) {
    //SS: Serve per superadmin
    $smarty->assign('USER_CAN_ADD_ALL_USERS', true);
}

if ($auth->hasPerm('ADD', 'LOCAL_USER')) {
    //SS: Serve per superadmin
    $smarty->assign('USER_CAN_ADD_LOCAL_USER', true);
}

$smarty->assign('canChangePassword', $canChangePassword);
$smarty->assign('canAddUser', $canAddUser);
$smarty->assign('canModUser', $canModUser);
$smarty->assign('canDelUser', $canDelUser);

/** Restore old variabled */
$fltdn_name =        pageVar('fltdn_name',   $auth->getConfigValue('USER_MANAGER', 'DEFAULT_DOMAIN'), isset($_REQUEST['reset']), false, 'general');
$fltapp_code =       pageVar('fltapp_code',  $auth->getConfigValue('USER_MANAGER', 'DEFAULT_APPLICATION'), isset($_REQUEST['reset']), false, 'general');
$gr_name =           pageVar('gr_name', null, isset($_REQUEST['reset']));
$us_status =         pageVar('us_status', 'E', isset($_REQUEST['reset']));
$login_name =        pageVar('login_name', null, isset($_REQUEST['reset']));
$order =             pageVar('order', '1A', isset($_REQUEST['reset']));

//echo "[$login_name]";
/** Special permissions */
$canShowDomains =      (($auth->hasPerm('SHOW', 'DOMAIN') || $auth->hasPerm('SHOW', 'ALL_DOMAINS')) &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_DOMAIN', 'T') != 'F');
$canShowApplications = (($auth->hasPerm('SHOW', 'APPLICATION') || $auth->hasPerm('SHOW', 'ALL_APPLICATIONS')) &&
                        $auth->getConfigValue('USER_MANAGER', 'SHOW_APPLICATION', 'T') != 'F');
$canShowUserApplications = $canShowDomains && $canShowApplications;

if (!$canShowDomains) {
    $fltdn_name = $auth->getDomainName();
}

$limit = max(10, $auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10));
$pg = max(1, PageVar('pg', 1, isset($_REQUEST['reset'])));
$st    = ($pg - 1) * $limit;


/** filters */
$filter_where = '';

/** Doamin filter */
$smarty->assign('fltdn_name', $fltdn_name);
if ($canShowDomains) {
    $performance_time[] = array('text'=>'Before domain filter: ', 'time'=>microtime(true));
    try{
        $smarty->assign('dn_name_list', $auth->mkAssociativeArray($auth->getDomainsList(), 'DOMAIN'));
    } catch (EPermissionDenied $e) {
        // echo $e->getMessage() . "\n";  
    } catch (EDatabaseError $e) {
        echo "Database error: " . $e->getMessage() . "\n";  
    } catch (Exception $e) {
        echo "Generic error: " . $e->getMessage() . "\n";      
    }
    $performance_time[] = array('text'=>'After domain filter: ', 'time'=>microtime(true));
}

/** Application filter */
if ($canShowApplications) {
    $performance_time[] = array('text'=>'Before application filter: ', 'time'=>microtime(true));
    try{
        $smarty->assign('fltapp_code', $fltapp_code);
        $smarty->assign('app_code_list', $auth->mkAssociativeArray($auth->getApplicationsList(array('order'=>'app_name')), 'APPLICATION'));
    } catch (EPermissionDenied $e) {
        //echo $e->getMessage() . "\n";  
    } catch (EDatabaseError $e) {
        echo "Database error: " . $e->getMessage() . "\n";  
    } catch (Exception $e) {
        echo "Generic error: " . $e->getMessage() . "\n";      
    }
    $performance_time[] = array('text'=>'After application filter: ', 'time'=>microtime(true));
}

/** Group filter */
try{
    $performance_time[] = array('text'=>'Before group filter: ', 'time'=>microtime(true));
    if ($gr_name != '') {
        $smarty->assign('gr_name', $gr_name);
        $a = explode('|', $gr_name);
        $group_where = "  gr_name = " . $db->quote($a[1]);
        $group_join = "  INNER JOIN " . $auth_options['users_groups_table'] . " ON " .
                      "    " . $auth_options['users_table'] . ".us_id=" . $auth_options['users_groups_table'] . ".us_id " . 
                      "  INNER JOIN " . $auth_options['groups_table'] . " ON " .
                      "    " . $auth_options['users_groups_table'] . ".gr_id=" . $auth_options['groups_table'] . ".gr_id ";
        $fltapp_code = $a[0];
        if ($fltapp_code != '') {
            $smarty->assign('fltapp_code', $fltapp_code);
        }               
    } else {
        $group_where = '1 = 1';
        $group_join = '';
    }
    if ($fltapp_code == '') {
        $smarty->assign('gr_name_list', $auth->mkAssociativeArray($auth->getGroupsList(), 'GROUP'));
    } else {
        $smarty->assign('gr_name_list', $auth->mkAssociativeArray($auth->getGroupsList($fltapp_code), 'GROUP'));
    }    
    $performance_time[] = array('text'=>'After group filter: ', 'time'=>microtime(true));
} catch (EPermissionDenied $e) {
    // echo $e->getMessage() . "\n";  
} catch (EDatabaseError $e) {
    echo "Database error: " . $e->getMessage() . "\n";  
} catch (Exception $e) {
    echo "Generic error: " . $e->getMessage() . "\n";      
}

// Authentication
if ($fltdn_name <> '') {
	$authSettings = $auth->getDomainData($fltdn_name, true);   
} else {
	$authSettings = $auth->getDomainData($auth->getDomainName(), true);   
}
if (!isset($authSettings['auth_settings'])) {
	$authSettings['auth_settings'] = array();
}
$extraFields = '';
$extraGroupFields = '';

$filter_where = ' 1=1 ';

/** login_name filter */
if ($login_name != '') {
    $filter_where .= ' AND us_login ilike ' . $db->quote('%' . $login_name . '%') . ' or us_name ilike ' . $db->quote('%' . $login_name . '%');
    $smarty->assign('login_name', $login_name);
}
if ($us_status != '') {
    $filter_where .= ' AND us_status ilike ' . $db->quote($us_status);
    $smarty->assign('us_status', $us_status);
}

/** List table */
$styleName = $auth->getConfigValue('USER_MANAGER', 'TABLE_STYLE', 'grid_wrap');

$table = new pSimpleTable("100%", $styleName);
$table->checkImage(false);
if ($auth->getConfigValue('USER_MANAGER', 'SHOW_UID') != 'F') {
    $table->addSimpleField('UID', 'us_id', 'INTEGER',      40,    array('visible'=>true,'align'=>'right', 'sortable'=>true));
}
$table->addSimpleField((!isset($hdr['Login']) ? _('Login') : $hdr['Login']), 'us_login',      'STRING',      150,   array('visible'=>true,'align'=>'left', 'sortable'=>true));
$table->addSimpleField((!isset($hdr['Nome']) ? _('Nome') : $hdr['Nome']), 'us_name',       'STRING',      null,  array('visible'=>true,'align'=>'left', 'sortable'=>true, 'order_fields'=>'us_name, us_login, us_id asc'));
if ($canShowDomains && $auth->getConfigValue('USER_MANAGER', 'SHOW_DOMAIN') != 'F') {
//SS: verificare permission    
    $table->addSimpleField((!isset($hdr['Domain']) ? _('Dominio') : $hdr['Domain']), 'dn_name', 'STRING',      100,  array('visible'=>true,'align'=>'left', 'sortable'=>true, 'order_fields'=>'dn_name, us_name, us_login, us_id asc'));
}
if ($auth->getConfigValue('USER_MANAGER', 'SHOW_GROUPS') != 'F') {
//SS: verificare permission
    $table->addSimpleField((!isset($hdr['Groups']) ? _('Gruppo') : $hdr['Groups']), 'groups', 'CALCULATED',  200,  array('visible'=>true,'align'=>'left', 'sortable'=>true, 'order_fields'=>'us_name, us_login, us_id asc'));
}
if ($canShowUserApplications && $auth->getConfigValue('USER_MANAGER', 'SHOW_APPLICATIONS') != 'F') {
    //SS: verificare permission
    $table->addSimpleField((!isset($hdr['Applications']) ? _('Applicazioni') : $hdr['Applications']), 'applications', 'CALCULATED',  200,  array('visible'=>true,'align'=>'left', 'sortable'=>true, 'order_fields'=>'us_name, us_login, us_id asc'));
}
if ($auth->getConfigValue('USER_MANAGER', 'SHOW_AUTH_SETTINGS') == 'T'|| (count($authSettings['auth_settings']) > 0 && $auth->getConfigValue('USER_MANAGER', 'SHOW_AUTH_SETTINGS') != 'F')) {
    $table->addSimpleField(_('Autenticazione'), 'as_name', 'STRING', 90, array('visible'=>true,'align'=>'center', 'sortable'=>true, 'order_fields'=>'as_name, us_id asc'));
	$group_join = " LEFT JOIN {$auth_options['auth_settings_table']} ON {$auth_options['users_table']}.as_id={$auth_options['auth_settings_table']}.as_id ";
	$extraFields .= ", COALESCE(as_name, 'Standard') AS as_name";
	$extraGroupFields .= ", as_name";
}
if ($auth->getConfigValue('USER_MANAGER', 'SHOW_STATUS') != 'F') {
    $table->addSimpleField((!isset($hdr['Stato']) ? _('Stato') : $hdr['Stato']), 'status', 'CALCULATED', 90, array('visible'=>true,'align'=>'center', 'sortable'=>true, 'order_fields'=>'us_login, us_id asc'));
}
$table->addSimpleField((!isset($hdr['action']) ? _('Azione') : $hdr['action']), '', 'LINK', 70);
			   
/** Get the users list */
//SS: Prende campi di altre tabelle
		  
$sql2 = "SELECT user_manager.us_id, us_login, us_name, us_status, dn_name {$extraFields} \n" .
        "FROM <SQL> \n" . 
        "INNER JOIN " . $auth_options['users_table'] . " ON user_manager.us_id=" . $auth_options['users_table'] . ".us_id \n" .
        "INNER JOIN " . $auth_options['domains_name_table'] . " ON " . $auth_options['users_table'] . ".do_id=" . $auth_options['domains_name_table'] . ".do_id \n" . 
        "$group_join \n" .
        "WHERE \n" .
        "  dn_type = 'N' AND \n" .
        "  $group_where \n" .        
        "GROUP BY user_manager.us_id, us_login, us_name, us_status, dn_name {$extraGroupFields} \n " .
        "ORDER BY " . $table->getSQLOrder();
$performance_time[] = array('text'=>'Before getUsersList time: ', 'time'=>microtime(true));
$list = $auth->getUsersList($fltdn_name, 
                            $fltapp_code, 
                            array('fields'=>'us_id', 
                                  'where'=>$filter_where,
                                  //'order'=>$table->getSQLOrder(),
                                  'offset'=>$st,
                                  'limit'=>$limit, 
                                  'sql'=>$sql2), 
                            $tot); 
$performance_time[] = array('text'=>'After getUsersList time: ', 'time'=>microtime(true));

$table_html = $table->CreateTableHeader($order);
$domain_applications = array();  /** cache the applications available for all the domains */

foreach($list as $value) {
    if ($value['us_status'] == 'E') {
        $table->AddCalcValue('status', (!isset($hdr['ENABLED']) ? _('ATTIVO') : $hdr['ENABLED']));
    } else if ($value['us_status'] == 'D') {
		$table->AddCalcValue('status', (!isset($hdr['DISABLED']) ? _('NON ATTIVO') : $hdr['DISABLED']));
	} else {
        $table->AddCalcValue('status', _('ELIMINATO'));
    }
    
    if ($auth->getConfigValue('USER_MANAGER', 'SHOW_GROUPS') != 'F') {
        /** Groups data required */
        $userData = $auth->getUserData($value['dn_name'], $fltapp_code, $value['us_login'], array('GROUPS'));
        $groups = array();
        if (is_array($userData['groups'])) {
            foreach ($userData['groups'] as $val) {
                if ($canShowApplications && $fltapp_code == '') {
                    $groups[] = $val['app_name'] . ' ' . $val['gr_name'];
                } else {
                    $groups[] = $val['gr_name'];
                }
            }
        }
        $table->AddCalcValue('groups', implode(', ', $groups));
    }
    
    if (($canShowUserApplications && $auth->getConfigValue('USER_MANAGER', 'SHOW_APPLICATIONS') != 'F')) {
        // /* Get the applications list */
        if (!isset($domain_applications[$value['dn_name']])) {
            $a = $auth->getDomainData($value['dn_name'], true);   
            $domain_applications[$value['dn_name']] = $a['applications'];
        }
        $applications = array();
        foreach ($domain_applications[$value['dn_name']] as $key => $val) {
            if ($auth->userHasPerm($value['dn_name'], $key, $value['us_login'], 'USE', 'APPLICATION')) {
                $applications[] = $val;
            }
        }
        $table->AddCalcValue('applications', implode(', ', $applications));
    }
    
    $links = array();
    $canMod = in_array($value['us_status'], array('E', 'D')) && ($auth->hasPerm('MOD', 'USER') || $auth->hasPerm('MOD', 'ALL_USERS') || $auth->hasPerm('MOD', 'LOCAL_USER'));
    $canDel = in_array($value['us_status'], array('E', 'D')) && ($auth->hasPerm('DEL', 'USER') || $auth->hasPerm('DEL', 'ALL_USERS') || $auth->hasPerm('DEL', 'LOCAL_USER'));
    
    $param = "dn_name={$value['dn_name']}&us_login={$value['us_login']}&";
	$defAct = "users_edit.php?act=show&{$param}";
    $links[] = $table->AddLinkCell((!isset($hdr['visualizza']) ? _('Visualizza') : $hdr['visualizza']), $defAct, '', R3_ICONS_URL . 'ico_view.gif');
    if ($canMod) {
		$defAct = "users_edit.php?act=mod&{$param}";
        $links[] = $table->AddLinkCell((!isset($hnt['edit']) ? _('Modifica') : $hnt['edit']), $defAct, '', R3_ICONS_URL . 'ico_edit.gif');
    } else {
        $links[] = $table->AddLinkCell('', '', '', R3_ICONS_URL . 'ico_spacer.gif');
    }
    if ($canDel) {
        $links[] = $table->AddLinkCell((!isset($hnt['delete']) ? _('Cancella') : $hnt['delete']), "JavaScript:askDel('" . $value['dn_name'] . "', '" . $value['us_login'] . "')", '', R3_ICONS_URL . 'ico_del.gif');
    } else {
        $links[] = $table->AddLinkCell('', '', '', R3_ICONS_URL . 'ico_spacer.gif');
    }
    
    $table_html .= $table->createTableRow($value, $links, null, array('ondblclick'=>"document.location='{$defAct}'"));
    $performance_time[] = array('text'=>'ROW: ', 'time'=>microtime(true));
}

$table_html .= $table->MkTableFooter();
$navigationBar_html = $table->mkNavigationBar($pg, $tot, $limit);

$smarty->assign('table_html', $table_html);
$smarty->assign('navigationBar_html', $navigationBar_html);
$smarty->assign('tot', $tot);

$smarty->display('users/users_list.tpl');
