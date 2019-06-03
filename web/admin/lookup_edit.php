<?php

$scriptStartTime = microtime(true);
define('R3_FAST_SESSION', true);
require_once dirname(__FILE__) . '/../../etc/config.php';
require_once R3_LIB_DIR . 'eco_app.php';
require_once R3_LIB_DIR . 'eco_utils.php';
require_once R3_LIB_DIR . 'storevar.php';
require_once R3_LIB_DIR . 'simpletable.php';
require_once R3_LANG_DIR . 'lang.php';
register_shutdown_function('shutdown');

/* ---------------- Startup ------------------------------------------------- */

R3AppStart('admin', array('auth' => true, 'auth_manager' => false));


/* ---------------- Initialization ------------------------------------------ */

require_once R3_LIB_DIR . 'obj.base_lookup.php';
$objName = R3LookupController::getObjectType($_REQUEST);
$objAction = R3LookupController::getObjectAction($_REQUEST);
$objId = R3LookupController::getObjectId($_REQUEST);
$objNameUC = strToUpper($objName);
$objActionUC = strToUpper($objAction);


/* ---------------- jqGrid translation -------------------------------------- */

if (defined('USE_JQGRID')) {
    if (!isset($_REQUEST['order']) &&
            isset($_REQUEST['sidx']) && isset($_REQUEST['sord'])) {
        $_REQUEST['order'] = "{$_REQUEST['sidx']}|{$_REQUEST['sord']}";
    }
}


/* ---------------- Factory ------------------------------------------------- */

$obj = R3LookupController::factory($_REQUEST);
$obj->setAuth($auth);

$obj->checkPerm();


/* ---------------- AJAX call ----------------------------------------------- */

$obj->processAjaxRequest();


/* ---------------- Page action --------------------------------------------- */

$pageTitle = $obj->getPageTitle();

$form = $obj->getFormDefinition();
$vlu = $obj->getDataAsLocale();
$lkp = $obj->getLookupData();
$jsFiles = $obj->getJSFiles();
$jsVars = $obj->getJSVars();
$vars = $obj->getPageVars();
$tpl = $obj->getTemplateName();


/* ---------------- Menu & standard smarty assignment ----------------------- */

$MenuItem = $objName . '_edit';
require_once(R3_WEB_ADMIN_DIR . 'smarty_assign.php');
require_once(R3_WEB_ADMIN_DIR . 'menu.php');


/* ---------------- Smarty assignment --------------------------------------- */

$smarty->assign('lang', $lang);
$smarty->assign('lang_code', $languages[$lang]);

$smarty->assign('object_name', $objName);
$smarty->assign('object_action', $objAction);
$smarty->assign('object_id', $objId);
$smarty->assign('page_title', $pageTitle);
$smarty->assign('js_files', $jsFiles);
$smarty->assign('js_vars', $jsVars);
$smarty->assign('vars', $vars);
$smarty->assign('act', $objAction);
$smarty->assign('vlu', $vlu);
$smarty->assign('lkp', $lkp);
$smarty->assign('form', $form);


if ($objAction == 'show') {
    $smarty->assign('input_status', "class=\"input_readonly\" readonly");
}

$smarty->display($tpl);
