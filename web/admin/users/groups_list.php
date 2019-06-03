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

if (!$auth->hasPerm('SHOW', 'GROUP') &&
    !$auth->hasPerm('SHOW', 'ALL_GROUPS')) {
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
$objAjax->registerExternalFunction('submitForm', 'groups_edit_ajax.php');
$objAjax->processRequests();
$smarty->assign('xajax_js_include', $objAjax->getJavascript(R3_JS_URL));


/** Restore old variabled */
$fltapp_code = pageVar('fltapp_code', null, isset($_REQUEST['reset']), false, 'general');
$order =      pageVar('order', '1A', isset($_REQUEST['reset']));

/** filters */
$filter_where = '';

if ($fltapp_code != '') {
    $smarty->assign('fltapp_code', $fltapp_code);
} else {
    $fltapp_code = null;
}

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

/** Group list (for export upourpose */
try{
    $data = array();
    if ($fltapp_code == '') {
        foreach($auth->getGroupsList() as $val) {
            $data[$val['app_code']][$val['app_code'] . '|' . $val['gr_name']] = $val['gr_name'];
        }
        $smarty->assign('export_gr_name_list', $data);
    } else {
        $smarty->assign('export_gr_name_list', $auth->mkAssociativeArray($auth->getGroupsList($fltapp_code), 'GROUP'));
    }    
} catch (EPermissionDenied $e) {
} catch (EDatabaseError $e) {
    echo "Database error: " . $e->getMessage() . "\n";  
} catch (Exception $e) {
    echo "Generic error: " . $e->getMessage() . "\n";      
}



/** List table */
$table = new pSimpleTable("100%", 'grid');
$table->checkImage(false);
$table->addSimpleField((!isset($hdr['CODE']) ? _('Codice') : $hdr['CODE']), 'app_name', 'STRING', 150,    array('visible'=>true, 'sortable'=>true));
$table->addSimpleField((!isset($hdr['Name']) ? _('Nome') : $hdr['Name']), 'gr_name', 'STRING', 150,    array('visible'=>true, 'sortable'=>true, 'sort_field'=>'app_name, gr_name'));
$table->addSimpleField((!isset($hdr['Privato']) ? _('Privato') : $hdr['Privato']), 'private', 'CALCULATED', 50,     array('visible'=>true, 'sortable'=>true));
$table->addSimpleField((!isset($hdr['Description']) ? _('Descrizione') : $hdr['Description']), 'gr_descr',      'STRING',     null,   array('visible'=>true, 'sortable'=>true));
$table->addSimpleField((!isset($hdr['action']) ? _('Azione') : $hdr['action']), '', 'LINK', 100);

$limit = max(10, $auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10));
$pg = max(1, PageVar('pg', 1, isset($_REQUEST['reset'])));
$st    = ($pg - 1) * $limit;

//SS: Prende campi di altre tabelle
$sql2 = "SELECT user_manager.*, COUNT(ugt.gr_id) AS us_tot \n" .
        "FROM <SQL> \n" . 
        "LEFT JOIN " . $auth_options['users_groups_table'] . " ugt ON user_manager.gr_id=ugt.gr_id \n" .
        "GROUP BY user_manager.gr_id, gr_name, gr_descr, do_id, app_code, app_name, dn_name  \n" .
        "ORDER BY " . $table->getSQLOrder();
        
/** Get the groups list */
$list = $auth->getGroupsList($fltapp_code, array('fields'=>'gr_id, gr_name, gr_descr, ' . 
                                                            $auth_options['groups_table'] . '.do_id, ' .
                                                            'app_code, app_name, dn_name', 
                                                 'offset'=>$st,
                                                 'limit'=>$limit,
                                                 'sql'=>$sql2),  $tot); 



$table_html = $table->CreateTableHeader($order);
foreach($list as $value) {

    $table->addCalcValue('private', $value['do_id'] == '' ? '' : (!isset($hdr['Si']) ? _('Si') : $hdr['Si']));
    $links = array();
    $canMod = ($auth->hasPerm('MOD', 'GROUP') || $auth->hasPerm('MOD', 'ALL_GROUP'));
    $canDel = ($auth->hasPerm('DEL', 'GROUP') || $auth->hasPerm('DEL', 'ALL_GROUP'));
    
    $param = "code={$value['app_code']}&name={$value['gr_name']}&";
	$defAct = "groups_edit.php?act=show&{$param}";
    $links[] = $table->AddLinkCell((!isset($hdr['visualizza']) ? _('Visualizza') : $hdr['visualizza']), $defAct, '', R3_ICONS_URL . 'ico_view.gif');
    if ($canMod) {
		$defAct = "groups_edit.php?act=mod&{$param}";
        $links[] = $table->AddLinkCell((!isset($hnt['edit']) ? _('Modifica') : $hnt['edit']), $defAct, '', R3_ICONS_URL . 'ico_edit.gif');
    } else {
        $links[] = $table->AddLinkCell('', '', '', R3_ICONS_URL . 'ico_spacer.gif');
    }
    if ($canDel) {
        $links[] = $table->AddLinkCell((!isset($hnt['delete']) ? _('Cancella') : $hnt['delete']), "JavaScript:askDel('" . $value['app_code'] . "', '" . $value['gr_name'] . "')", '', R3_ICONS_URL . 'ico_del.gif');
    } else {
        $links[] = $table->AddLinkCell('', '', '', R3_ICONS_URL . 'ico_spacer.gif');
    }
    
    $style = array();
    if ($value['us_tot'] == 0) {
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

$smarty->display('users/groups_list.tpl');
