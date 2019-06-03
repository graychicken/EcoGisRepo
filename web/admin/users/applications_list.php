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

if (!$auth->hasPerm('SHOW', 'APPLICATION') &&
    !$auth->hasPerm('SHOW', 'ALL_APPLICATIONS')) {
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
$objAjax->registerExternalFunction('submitForm', 'applications_edit_ajax.php');
$objAjax->processRequests();
$smarty->assign('xajax_js_include', $objAjax->getJavascript(R3_JS_URL));


/** Restore old variabled */
$order =      pageVar('order', '1A', isset($_REQUEST['reset']));

/** filters */
$filter_where = '';

/** List table */
$table = new pSimpleTable("100%", 'grid');
$table->checkImage(false);
$table->addSimpleField((!isset($hdr['CODE']) ? _('Codice') : $hdr['CODE']), 'app_code', 'STRING', 150, array('visible'=>true, 'sortable'=>true, 'order_fields'=>'app_code, app_name'));
$table->addSimpleField((!isset($hdr['Description']) ? _('Descrizione') : $hdr['Description']), 'app_name', 'STRING', null, array('visible'=>true, 'sortable'=>true, 'order_fields'=>'app_name, app_code'));
$table->addSimpleField((!isset($hdr['action']) ? _('Azione') : $hdr['action']), '', 'LINK', 100);
			   
$limit = max(10, $auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10));
$pg = max(1, PageVar('pg', 1, isset($_REQUEST['reset'])));
$st    = ($pg - 1) * $limit;

$list = $auth->getApplicationsList(array('order'=>$table->getSQLOrder($order),
                                         'offset'=>$st,
                                         'limit'=>$limit),  $tot); 
                            
$table_html = $table->CreateTableHeader($order);
foreach($list as $value) {
    
    $links = array();
    $canMod = ($auth->hasPerm('MOD', 'APPLICATION') || $auth->hasPerm('MOD', 'ALL_APPLICATIONS'));
    $canDel = ($auth->hasPerm('DEL', 'APPLICATION') || $auth->hasPerm('DEL', 'ALL_APPLICATIONS'));
    
	$defAct = "applications_edit.php?act=show&cod={$value['app_code']}";
    $links[] = $table->AddLinkCell((!isset($hdr['visualizza']) ? _('Visualizza') : $hdr['visualizza']), $defAct, '', R3_ICONS_URL . 'ico_view.gif');
    if ($canMod) {
		$defAct = "applications_edit.php?act=mod&cod={$value['app_code']}";
        $links[] = $table->AddLinkCell((!isset($hnt['edit']) ? _('Modifica') : $hnt['edit']), $defAct, '', R3_ICONS_URL . 'ico_edit.gif');
    } else {
        $links[] = $table->AddLinkCell('', '', '', R3_ICONS_URL . 'ico_spacer.gif');
    }
    if ($canDel) {
        $links[] = $table->AddLinkCell((!isset($hnt['delete']) ? _('Cancella') : $hnt['delete']), "JavaScript:askDel('" . $value['app_code'] . "')", '', R3_ICONS_URL . 'ico_del.gif');
    } else {
        $links[] = $table->AddLinkCell('', '', '', R3_ICONS_URL . 'ico_spacer.gif');
    }
    $table_html .= $table->createTableRow($value, $links, null, array('ondblclick'=>"document.location='{$defAct}'"));
}
  
$table_html .= $table->MkTableFooter();
$navigationBar_html = $table->mkNavigationBar($pg, $tot, $limit);

$smarty->assign('tot', $tot);
$smarty->assign('table_html', $table_html);
$smarty->assign('navigationBar_html', $navigationBar_html);

$smarty->display('users/applications_list.tpl');
