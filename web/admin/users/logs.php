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
require_once R3_LIB_DIR . 'simpletable.php';
require_once R3_LIB_DIR . 'storevar.php';
require_once R3_APP_ROOT . 'lang/lang.php';



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

if (!$auth->hasPerm('SHOW', 'LOG')) {
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

/** Restore old variabled */
$fltdn_name =     pageVar('fltdn_name',      $auth->getConfigValue('USER_MANAGER', 'DEFAULT_DOMAIN'), isset($_REQUEST['reset']), false, 'general');
$fltapp_code =    pageVar('fltapp_code',     $auth->getConfigValue('USER_MANAGER', 'DEFAULT_APPLICATION'), isset($_REQUEST['reset']), false, 'general');
$fltlogin_name =  pageVar('fltlogin_name',   null, isset($_REQUEST['reset']));
$fltdate_from =   pageVar('fltdate_from',    null, isset($_REQUEST['reset']));
$fltdate_to =     pageVar('fltdate_to',      null, isset($_REQUEST['reset']));
$fltip =          pageVar('fltip',           null, isset($_REQUEST['reset']));
$flterror =       pageVar('flterror',        initVar('flterror', isset($_REQUEST['reset']) ? 'T' : 'F'), isset($_REQUEST['reset']) || isset($_REQUEST['apply_filter']));
$fltwarn =        pageVar('fltwarn',         initVar('fltwarn',  isset($_REQUEST['reset']) ? 'T' : 'F'), isset($_REQUEST['reset']) || isset($_REQUEST['apply_filter']));
$fltinfo =        pageVar('fltinfo',         initVar('fltinfo',  isset($_REQUEST['reset']) ? 'T' : 'F'), isset($_REQUEST['reset']) || isset($_REQUEST['apply_filter']));
$fltdebug =       pageVar('fltdebug ',       initVar('fltdebug', isset($_REQUEST['reset']) ? 'F' : 'F'), isset($_REQUEST['reset']) || isset($_REQUEST['apply_filter']));
$fltauth =        pageVar('fltauth',         initVar('fltauth',  isset($_REQUEST['reset']) ? 'T' : 'F'), isset($_REQUEST['reset']) || isset($_REQUEST['apply_filter']));
$fltnavigation =  pageVar('fltnavigation',   initVar('fltnavigation', isset($_REQUEST['reset']) ? 'F' : 'F'), isset($_REQUEST['reset']) || isset($_REQUEST['apply_filter']));
$fltlimit =       PageVar('fltlimit',        max(10, $auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10)), isset($_REQUEST['reset']));
$order =          pageVar('order',           '1D', isset($_REQUEST['reset']));
$pg = max(1, PageVar('pg', 1, isset($_REQUEST['reset'])));
$st    = ($pg - 1) * $fltlimit;

if ($flterror == 'F' && $fltwarn == 'F' && $fltinfo == 'F' && $fltdebug == 'F') {
	$flterror = 'T';
	$fltwarn = 'T';
}
if ($fltauth == 'F' && $fltnavigation == 'F') {
	$fltauth = 'T';
}

if ($fltdn_name == '') {
    $fltdn_name = null;
}
if ($fltapp_code == '') {
    $fltapp_code = null;
}

$canShowDomains =       ($auth->hasPerm('SHOW', 'DOMAIN') || $auth->hasPerm('SHOW', 'ALL_DOMAINS'));
$canShowApplications =  ($auth->hasPerm('SHOW', 'APPLICATION') || $auth->hasPerm('SHOW', 'ALL_APPLICATIONS'));

/** filters */
$filter_where = '1=1';

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

/** login/name filter */
if ($fltlogin_name != '') {
    $filter_where .= ' AND (us_login ilike ' . $db->quote('%' . $fltlogin_name . '%') . ' or us_name ilike ' . $db->quote('%' . $fltlogin_name . '%') . ')';
    $smarty->assign('fltlogin_name', $fltlogin_name);
}

/** date filter */
$date_from = ISOToDate($fltdate_from);
if ($date_from != '') {
    $filter_where .= ' AND log_time>=' . $db->quote($date_from);
}
$date_to = ISOToDate($fltdate_to);
if ($date_to != '') {
    $filter_where .= ' AND log_time<=' . $db->quote($date_to . ' 23:59:59');
}

/** ip filter */
if ($fltip != '') {
    $filter_where .= ' AND log_ip ilike ' . $db->quote('%' . $fltip . '%');
    $smarty->assign('fltip', $fltip);
}

/** type filter */
$type = array();
$auth_type = array();
if ($flterror == 'T') {
    $type[] = 'C';
    $type[] = 'E';
}
if ($fltwarn == 'T') {
    $type[] = 'W';
}
if ($fltinfo == 'T') {
    $type[] = 'I';
    $type[] = 'N';
}
if ($fltdebug == 'T') {
    $type[] = 'D';
}

if ($fltauth == 'T') {
    $auth_type[] = 'I';
    $auth_type[] = 'O';
    $auth_type[] = 'X';
}
if ($fltnavigation == 'T') {
    $auth_type[] = 'N';
    $auth_type[] = null;
}

$filter_where .= " AND log_type IN ('" . implode("', '", $type) . "')";
$filter_where .= " AND log_auth_type IN ('" . implode("', '", $auth_type) . "')";

$smarty->assign('flterror', $flterror);
$smarty->assign('fltwarn', $fltwarn);
$smarty->assign('fltinfo', $fltinfo);
$smarty->assign('fltdebug', $fltdebug);
$smarty->assign('fltauth', $fltauth);
$smarty->assign('fltnavigation', $fltnavigation);

/** limit filter */
$limit_list = array(10=>10, 25=>25, 50=>50, 100=>100, 250=>250, 500=>500, 1000=>1000);
if (!in_array($auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10), $limit_list)) {
    $limit_list[$auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10)] = $auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10);
    asort($limit_list);
}
$smarty->assign('fltlimit', $fltlimit);
$smarty->assign('limit_list', $limit_list);



/** List table */
$table = new pSimpleTable("100%", 'grid');
$table->checkImage(false);
//$table->addSimpleField('log_id',                      'log_id',     'INTEGER',   60,   array('visible'=>true,'align'=>'right', 'sortable'=>true));//
$table->addSimpleField((!isset($hdr['logs_date']) ? _('Data/ora') : $hdr['logs_date']),                        'log_time',   'DATETIME',  140,  array('align'=>'center', 'sortable'=>true, 'order_fields'=>'log_time, log_id'));
$table->addSimpleField(_('Tipo'), 'log_type',   'TEXT',  40,  array('align'=>'center', 'sortable'=>true, 'order_fields'=>'log_type, log_id'));
$table->addSimpleField((!isset($hdr["logs_login"]) ? _('Login') : $hdr["logs_login"]),                         'us_login',   'STRING',    100,  array('visible'=>true,'align'=>'left', 'sortable'=>true, 'order_fields'=>'us_login, log_id'));
//$table->addSimpleField('Nome',                        'us_name',    'STRING',    null, array('visible'=>true,'align'=>'left', 'sortable'=>true, 'order_fields'=>'us_name, us_login, log_id'));//
if ($fltdn_name == '' && $canShowDomains && $auth->getConfigValue('USER_MANAGER', 'SHOW_DOMAIN') != 'F') {
    $table->addSimpleField((!isset($hdr["logs_domain"]) ? _('Dominio') : $hdr["logs_domain"]),                 'dn_name',    'STRING',    150,  array('visible'=>true,'align'=>'left', 'sortable'=>true, 'order_fields'=>'dn_name, log_time, log_id'));
}
if ($fltapp_code == '' && $canShowApplications && $auth->getConfigValue('USER_MANAGER', 'SHOW_APPLICATION') != 'F') {
    $table->addSimpleField((!isset($hdr["logs_application"]) ? _('Applicazione') : $hdr["logs_application"]),  'app_name',   'STRING',    150,  array('visible'=>true,'align'=>'left', 'sortable'=>true, 'order_fields'=>'app_name, log_time, log_id'));
}
$table->addSimpleField((!isset($hdr["logs_ip"]) ? _('IP') : $hdr["logs_ip"]),                                  'log_ip',     'STRING',    100,  array('visible'=>true,'align'=>'center', 'sortable'=>true, 'order_fields'=>'log_ip, us_name, us_login, log_ip'));
$table->addSimpleField((!isset($hdr["logs_page"]) ? _('Pagina') : $hdr["logs_page"]),                          'log_page',   'STRING',    null, array('visible'=>true,'align'=>'left', 'sortable'=>true, 'order_fields'=>'log_page, us_name, us_login, log_ip'));
$table->addSimpleField((!isset($hdr["logs_note"]) ? _('Note') : $hdr["logs_note"]),                            'log_text',   'STRING');
			   

$table_html = $table->CreateTableHeader($order);


$list = $auth->getLogList($fltdn_name, $fltapp_code, array('order'=>$table->getSQLOrder($order),
                                                          'offset'=>$st,
                                                          'where'=>$filter_where,
                                                          'limit'=>$fltlimit), $tot); 

foreach($list as $value) {
    
    $style = array();
    switch ($value['log_type']) {
        case 'C':
        case 'E':
            $style['normal'] = 'grid_error';
            $style['over']   = 'grid_error_over';
        break;
        case 'W':
            $style['normal'] = 'grid_warning';
            $style['over']   = 'grid_warning_over';
        break;
        case 'D':
            $style['normal'] = 'grid_debug';
            $style['over']   = 'grid_debug_over';
        break;
    }
    $table_html .= $table->createTableRow($value, null, $style);
}
  
$table_html .= $table->MkTableFooter();
$navigationBar_html = $table->mkNavigationBar($pg, $tot, $fltlimit);

$smarty->assign('tot', $tot);
$smarty->assign('table_html', $table_html);
$smarty->assign('navigationBar_html', $navigationBar_html);
if (!defined('R3_UM_JQUERY') || !R3_UM_JQUERY) {
    $smarty->assign('fltdate_from', createDateInput($fltdate_from, 'fltdate_from', 'fltdate_from'));
    $smarty->assign('fltdate_to', createDateInput($fltdate_to, 'fltdate_to', 'fltdate_to'));
    $smarty->assign('calendarHeader', GetCalendarHeader());
} else {
    $smarty->assign('fltdate_from', $fltdate_from);
    $smarty->assign('fltdate_to', $fltdate_to);
}

$smarty->display('users/logs.tpl');
