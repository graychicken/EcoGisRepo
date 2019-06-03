<?php
$isUserManager = true;

require_once '../../../etc/config.php';
if (file_exists(R3_APP_ROOT . 'lib/r3_auth_gui_start.php')) {
    require_once R3_APP_ROOT . 'lib/r3_auth_gui_start.php';
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

if (!$auth->hasPerm('SHOW', 'CONNECTED_USER')) {
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


function disconnectUser($dn_name, $us_login) {
    global $auth;
    
    $objResponse = new xajaxResponse();
    $auth->disconnectUser($dn_name, $us_login);
    $objResponse->addScript("document.location='connected_users.php'");
    return $objResponse->getXML();
    
}

/** Ajax request */
if (defined('R3_USERMANAGER_RELATIVE_LINKS') && R3_USERMANAGER_RELATIVE_LINKS) {
	$url = 'applications_edit.php';
	$p = strpos($_SERVER['REQUEST_URI'], '?');
	if ($p > 0) {
		$url .= substr($_SERVER['REQUEST_URI'], $p);
	}
} else {
	$url = R3_DOMAIN_URL . $_SERVER['REQUEST_URI'];
}
$url .= (strpos($url, '?') === false ? '?' : '&') . 'proxytime=' . md5(time());

$objAjax = new xajax($url);
$objAjax->registerExternalFunction('disconnectUser', 'applications_edit_ajax.php');
$objAjax->processRequests();
$smarty->assign('xajax_js_include', $objAjax->getJavascript(R3_JS_URL));

$order =      pageVar('order', '1A', isset($_REQUEST['reset']));
$limit = max(10, $auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10));
$pg = max(1, PageVar('pg', 1, isset($_REQUEST['reset'])));
$st    = ($pg - 1) * $limit;

/** filters */
$filter_where = '';

$canShowDomains =      ($auth->hasPerm('SHOW', 'DOMAIN') || $auth->hasPerm('SHOW', 'ALL_DOMAINS'));

/** List table */
$table = new pSimpleTable("100%", 'grid');
$table->checkImage(false);
if ($auth->getConfigValue('USER_MANAGER', 'SHOW_UID') != 'F') {
    $table->addSimpleField('UID',            'us_id',           'INTEGER',      60,  array('visible'=>true,'align'=>'right', 'sortable'=>true));
}
$table->addSimpleField((!isset($hdr['Login']) ? _('Login') : $hdr['Login']),              'us_login',        'STRING',      150,  array('visible'=>true,'align'=>'left', 'sortable'=>true));
$table->addSimpleField((!isset($hdr['Nome']) ? _('Nome') : $hdr['Nome']),               'us_name',         'STRING',      null, array('visible'=>true,'align'=>'left', 'sortable'=>true, 'order_fields'=>'us_name, us_login, us_id asc'));
if ($canShowDomains && $auth->getConfigValue('USER_MANAGER', 'SHOW_DOMAIN') != 'F') {
    //SS: verificare permission    
    $table->addSimpleField((!isset($hdr['Domain']) ? _('Dominio') : $hdr['Domain']),         'dn_name',         'STRING',      100,  array('visible'=>true,'align'=>'left', 'sortable'=>true, 'order_fields'=>'us_name, us_login, us_id asc'));
}
$table->addSimpleField('IP',                 'us_last_ip',      'STRING',      100,  array('visible'=>true,'align'=>'center', 'sortable'=>true, 'order_fields'=>'us_last_ip, us_name, us_login, us_id asc'));
$table->addSimpleField((!isset($hdr['login_time']) ? _('Ora login') : $hdr['login_time']),          'us_last_login',   'DATETIME',    150,  array('visible'=>true,'align'=>'center', 'sortable'=>true, 'order_fields'=>'us_last_login, us_name, us_login, us_id asc'));
$table->addSimpleField((!isset($hdr['last_login_time']) ? _('Ora ultima azione') : $hdr['last_login_time']),  'us_last_action',  'DATETIME',    150,  array('visible'=>true,'align'=>'center', 'sortable'=>true, 'order_fields'=>'us_last_action, us_last_login, us_name, us_login, us_id asc'));
if ($auth->hasPerm('DISCONNECT', 'USER')) {
    $table->addSimpleField((!isset($hdr['action']) ? _('Azione') : $hdr['action']),      '',              'LINK',        20);
}
/** Get the users list */
                                  
$list = $auth->getConnectedUsersList(null, null, array('order'=>$table->getSQLOrder(),
                                                       'offset'=>$st,
                                                       'limit'=>$limit), $tot); 

$table_html = $table->CreateTableHeader($order);
foreach($list as $value) {
    
    $links = array();
    $canDel = $auth->hasPerm('DISCONNECT', 'USER') && $value['us_id'] <> $auth->getUID();
    if ($canDel) {
        $links[] = $table->AddLinkCell((!isset($hdr['Disconnetti']) ? _('Disconnetti') : $hdr['Disconnetti']), "JavaScript:askDisconnect('" . $value['dn_name'] . "', '" . $value['us_login'] . "')", '', R3_ICONS_URL . 'ico_disconnect.gif');
    } else {
        $links[] = $table->AddLinkCell('', '', '', R3_ICONS_URL . 'ico_spacer.gif');
    }
    $table_html .= $table->createTableRow($value, $links);
}
  
$table_html .= $table->MkTableFooter();
$navigationBar_html = $table->mkNavigationBar($pg, $tot, $limit);

$smarty->assign('tot', $tot);
$smarty->assign('table_html', $table_html);
$smarty->assign('navigationBar_html', $navigationBar_html);

$smarty->display('users/connected_users.tpl');
