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

if (!$auth->hasPerm('SHOW', 'DOMAIN') &&
    !$auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
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
$objAjax->registerExternalFunction('submitForm', 'domains_edit_ajax.php');
$objAjax->processRequests();
$smarty->assign('xajax_js_include', $objAjax->getJavascript(R3_JS_URL));


$order =      pageVar('order', '1A', isset($_REQUEST['reset']));

/** filters */
$filter_where = '';

/** List table */
$table = new pSimpleTable("100%", 'grid');
$table->checkImage(false);
$table->addSimpleField((!isset($hdr['Name']) ? _('Nome') : $hdr['Name']), 'do_name', 'CALCULATED',     null,    array('visible'=>true, 'sortable'=>true, 'order_fields'=>'do_name, do_auth_type'));
$table->addSimpleField((!isset($hdr['Alias']) ? _('Alias') : $hdr['Alias']), 'do_alais', 'CALCULATED',      null,    array('visible'=>true));
$table->addSimpleField((!isset($hdr['Applications']) ? _('Applicativi') : $hdr['Applications']), 'do_applications',   'CALCULATED',      null,    array('visible'=>true));
$table->addSimpleField((!isset($hdr['action']) ? _('Azione') : $hdr['action']),      '',   'LINK',      100);

$limit = max(10, $auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10));
$pg = max(1, PageVar('pg', 1, isset($_REQUEST['reset'])));
$st    = ($pg - 1) * $limit;
			   
/** Get the users list */
$list = $auth->getDomainsList(array('fields'=>'dn_name as do_name, do_auth_type', 
                                    'order'=>$table->getSQLOrder($order),
                                    'offset'=>$st,
                                    'limit'=>$limit), $tot); 
$table_html = $table->CreateTableHeader($order);
foreach($list as $value) {

    // Get the lockup data
    $data = $auth->getDomainData($value['do_name'], true); 
    $table->addCalcValue('do_name', $data['names'][0]); /** The 1st element is the name, the others are alias) */
    
    // Alias
    $alias = array();
    for ($i = 1; $i < count($data['names']); $i++) {
        $alias[] = $data['names'][$i];
    }
    $s = implode(', ', $alias);
    $table->addCalcValue('do_alais', $s, $s); 
    
    // Applications
    $apps = array();
    foreach ($data['applications'] as $val) {
        $apps[] = $val;
    }
    $s = implode(', ', $apps);
    $table->addCalcValue('do_applications', $s, $s); 
    

    $links = array();
    $canMod = ($auth->hasPerm('MOD', 'DOMAIN') || $auth->hasPerm('MOD', 'ALL_DOMAINS'));
    $canDel = $auth->hasPerm('DEL', 'ALL_DOMAINS');  //SS: DEL DOMAIN DOESN'T EXISTS
    
	$defAct = "domains_edit.php?act=show&name={$value['do_name']}";
    $links[] = $table->AddLinkCell('visualizza', $defAct, '', R3_ICONS_URL . 'ico_view.gif');
    if ($canMod) {
		$defAct = "domains_edit.php?act=mod&name={$value['do_name']}";
        $links[] = $table->AddLinkCell((!isset($hnt['edit']) ? _('Modifica') : $hnt['edit']), $defAct, '', R3_ICONS_URL . 'ico_edit.gif');
    } else {
        $links[] = $table->AddLinkCell('', '', '', R3_ICONS_URL . 'ico_spacer.gif');
    }
    if ($canDel) {
        $links[] = $table->AddLinkCell((!isset($hnt['delete']) ? _('Cancella') : $hnt['delete']), "JavaScript:askDel('" . $value['do_name'] . "')", '', R3_ICONS_URL . 'ico_del.gif');
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

$smarty->display('users/domains_list.tpl');
