<?php
$isUserManager = true;

require_once '../../../etc/config.php';
if (file_exists(R3_APP_ROOT . 'lib/r3_auth_gui_start.php')) {
    require_once R3_APP_ROOT . 'lib/r3_auth_gui_start.php';
}
if (!defined("__R3_AUTH__")){
    require_once R3_APP_ROOT . 'lib/r3auth.php';
}
require_once R3_APP_ROOT . 'lib/r3auth_text.php';
require_once R3_APP_ROOT . 'lib/r3auth_manager.php';
require_once R3_APP_ROOT . 'lib/default.um.php';
require_once R3_APP_ROOT . 'lib/simpletable.php';
require_once R3_APP_ROOT . 'lib/storevar.php';
require_once R3_APP_ROOT . 'lib/xajax.php';
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
}

if (!$auth->hasPerm('SHOW', 'ACNAME') &&
    !$auth->hasPerm('SHOW', 'ALL_ACNAMES')) {
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
$objAjax->registerExternalFunction('submitForm', 'acnames_edit_ajax.php');
$objAjax->processRequests();
$smarty->assign('xajax_js_include', $objAjax->getJavascript(R3_JS_URL));


/** Restore old variabled */
$fltapp_code = pageVar('fltapp_code', null, isset($_REQUEST['reset']), false, 'general');
$fltac_verb =  pageVar('fltac_verb',  null, isset($_REQUEST['reset']), false, 'general');
$fltac_name =  pageVar('fltac_name',  null, isset($_REQUEST['reset']), false, 'general');
$fltac_type =  pageVar('fltac_type',  null, isset($_REQUEST['reset']), false, 'general');
$order =       pageVar('order', '1A', isset($_REQUEST['reset']));


/** filters */

$filter_where = '1 = 1';
/** Application filter */
if ($fltapp_code != '') {
    $smarty->assign('fltapp_code', $fltapp_code);
}
//$smarty->assign('app_code_list', $auth->mkAssociativeArray($auth->getApplicationsList(array('order'=>'app_name')), 'APPLICATION'));


/** Applications list */
$app_code_list = array();
try {
    $app_code_list = $auth->mkAssociativeArray($auth->getApplicationsList(array('order'=>'app_name')), 'APPLICATION');
} catch (EPermissionDenied $e) {
    $app_code_list = array($auth->application=>$auth->application);
    $fltapp_code = $auth->application;    
}
if (!$auth->hasPerm('SHOW', 'ALL_APPLICATIONS') && count($app_code_list) <= 1) {
    $fltapp_code = $auth->application;
}
$smarty->assign('app_code_list', $app_code_list);

/** verb filter */
if ($fltac_verb != '') {
    $smarty->assign('fltac_verb', $fltac_verb);
    $filter_where .= 'AND ac_verb=' . $db->quote($fltac_verb);
} else {
    $fltac_verb = null;
}
$verb_list = array();
foreach($auth->getDistinctACNamesList($fltapp_code, 'ac_verb') as $value) {
    $verb_list[$value['ac_verb']] = $value['ac_verb'];
}
$smarty->assign('ac_verb_list', $verb_list);

/** name filter */
if ($fltac_name != '') {
    $smarty->assign('fltac_name', $fltac_name);
    $filter_where .= 'AND ac_name=' . $db->quote($fltac_name);
} else {
    $fltac_name = null;
}
$name_list = array();
foreach($auth->getDistinctACNamesList($fltapp_code, 'ac_name') as $value) {
    $name_list[$value['ac_name']] = $value['ac_name'];
}
$smarty->assign('ac_name_list', $name_list);

/** type filter */
if ($fltac_type != '') {
    $smarty->assign('fltac_type', $fltac_type);
    $filter_where .= 'AND ac_type=' . $db->quote($fltac_type);
} else {
    $fltac_type = null;
}
$acTypeList = array();
try {
    $acTypeList = $auth->getACNamesTypeList();
    foreach($acTypeList as $key => $val)
      $acTypeList[$key] = $authText["user_manager_acname_type_$key"];
} catch (EPermissionDenied $e) {
    $acTypeList = null;
}

$smarty->assign('ac_type_list', $acTypeList);



/** List table */
$table = new pSimpleTable("100%", 'grid');
$table->checkImage(false);
$table->addSimpleField((!isset($hdr['Applicatione']) ? _('Applicativo') : $hdr['Applicatione']), 'app_name', 'STRING',     150,    array('visible'=>true, 'sortable'=>true, 'order_fields'=>'app_name, ac_verb, ac_name'));
$table->addSimpleField((!isset($hdr['Verbo']) ? _('Verbo') : $hdr['Verbo']), 'ac_verb', 'STRING', 150,    array('visible'=>true, 'sortable'=>true, 'order_fields'=>'ac_verb, ac_name, app_name'));
$table->addSimpleField((!isset($hdr['Oggetto']) ? _('Oggetto') : $hdr['Oggetto']), 'ac_name', 'STRING', 150,    array('visible'=>true, 'sortable'=>true, 'order_fields'=>'ac_name, app_name, ac_verb'));
$table->addSimpleField((!isset($hdr['Descrizione']) ? _('Descrizione') : $hdr['Descrizione']), 'ac_descr',     'STRING',     null,   array('visible'=>true, 'sortable'=>true, 'order_fields'=>'ac_descr, app_name, ac_verb, ac_name'));
$table->addSimpleField((!isset($hdr['action']) ? _('Azione') : $hdr['action']), '', 'LINK', 100);
			   
$limit = max(10, $auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10));
$pg = max(1, PageVar('pg', 1, isset($_REQUEST['reset'])));
$st    = ($pg - 1) * $limit;

//SS: Prende campi di altre tabelle
$sql2 = "SELECT user_manager.*, COUNT(gat.ac_id) AS gr_tot, COUNT(uat.ac_id) AS us_tot \n" .
        "FROM <SQL> \n" . 
        "LEFT JOIN " . $auth_options['groups_acl_table'] . " gat ON user_manager.ac_id=gat.ac_id \n" .
        "LEFT JOIN " . $auth_options['users_acl_table'] . " uat ON user_manager.ac_id=uat.ac_id \n" .
        "GROUP BY user_manager.ac_id, app_name, app_code, ac_verb, ac_name, ac_type, ac_active, ac_descr \n" .
        "ORDER BY " . $table->getSQLOrder();
        
$list = $auth->getACNamesList($fltapp_code, 
                              array('fields'=>'ac_id, app_name, app_code, ac_verb, ac_name, ac_type, ac_active, ac_descr',
                                    'offset'=>$st,
                                    'limit'=>$limit, 
                                    'sql'=>$sql2,
                                    'where'=>$filter_where),
                             $tot); 


$table_html = $table->CreateTableHeader($order);
foreach($list as $value) {
    
    $links = array();
    $canMod = ($auth->hasPerm('MOD', 'ACNAME') || $auth->hasPerm('MOD', 'ALL_ACNAME'));
    $canDel = ($auth->hasPerm('DEL', 'ACNAME') || $auth->hasPerm('DEL', 'ALL_ACNAME'));
    
    $params = "app_code={$value['app_code']}&ac_verb={$value['ac_verb']}&ac_name={$value['ac_name']}&";
	$defAct = "acnames_edit.php?act=show&{$params}";
    $links[] = $table->AddLinkCell((!isset($hdr['visualizza']) ? _('Visualizza') : $hdr['visualizza']), $defAct, '', R3_ICONS_URL . 'ico_view.gif');
    if ($canMod) {
		$defAct = "acnames_edit.php?act=mod&{$params}";
        $links[] = $table->AddLinkCell((!isset($hnt['edit']) ? _('Modifica') : $hnt['edit']), $defAct, '', R3_ICONS_URL . 'ico_edit.gif');
    } else {
        $links[] = $table->AddLinkCell('', '', '', R3_ICONS_URL . 'ico_spacer.gif');
    }
    if ($canDel) {
        $links[] = $table->AddLinkCell((!isset($hnt['delete']) ? _('Cancella') : $hnt['delete']), "JavaScript:askDel('" . $value['app_code'] . "', '" . $value['ac_verb'] . "', '" . $value['ac_name'] . "')", '', R3_ICONS_URL . 'ico_del.gif');
    } else {
        $links[] = $table->AddLinkCell('', '', '', R3_ICONS_URL . 'ico_spacer.gif');
    }
    
    $style = array();
    if ($value['gr_tot'] == 0 && $value['us_tot'] == 0) {
        $style['normal'] = 'grid_debug';
        $style['over']   = 'grid_debug_over';
    }
    $table_html .= $table->createTableRow($value, $links, $style, array('ondblclick'=>"document.location='{$defAct}'"));
}
  
$table_html .= $table->MkTableFooter();
$navigationBar_html = $table->mkNavigationBar($pg, $tot, $limit);

$smarty->assign('tot', $tot);
$smarty->assign('table_html', $table_html);
$smarty->assign('navigationBar_html', $navigationBar_html);

$smarty->display('users/acnames_list.tpl');
