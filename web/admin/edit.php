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

require_once R3_LIB_DIR . 'obj.base.php';
$objName = R3Controller::getObjectType($_REQUEST);
$objAction = R3Controller::getObjectAction($_REQUEST);
$objId = R3Controller::getObjectId($_REQUEST);
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

$obj = R3Controller::factory($_REQUEST);
$obj->setAuth($auth);

if (is_callable(array($obj, 'checkPerm'))) {
    $obj->checkPerm();
} else {
    if (!$auth->hasPerm($objActionUC, $objNameUC)) {
        die(sprintf(_("PERMISSION DENIED [%s/%s]"), $objActionUC, $objNameUC));
    };
}


/* ---------------- AJAX call ----------------------------------------------- */

$obj->processAjaxRequest();


/* ---------------- Page action --------------------------------------------- */

$pageTitle = $obj->getPageTitle();

// Needed for normal form submition (E.g.: file upload)
if ($obj->isApplyingData()) {
    $obj->applyData();
    $applyData = true;
}

$tabs = $obj->getTabs();
try {
    $vlu = $obj->getDataAsLocale();
} catch (Exception $e) {
    var_dump($e);
    echo "Fatal error: " . $e->getMessage();
    die();
}

$lkp = $obj->getLookupData();
$jsFiles = $obj->getJSFiles();
$jsVars = $obj->getJSVars();
$vars = $obj->getPageVars();
$tpl = $obj->getTemplateName();
$previewMap = $obj->getPreviewMap();
$hasDialogMap = $obj->hasDialogMap();


/* ---------------- Menu & standard smarty assignment ----------------------- */

$MenuItem = $objName . '_edit';
require_once(R3_WEB_ADMIN_DIR . 'smarty_assign.php');
require_once(R3_WEB_ADMIN_DIR . 'menu.php');


/* ---------------- Smarty assignment --------------------------------------- */


if (defined('GISCLIENT') && GISCLIENT == true && $hasDialogMap) {
    // GISCLIENT
    require_once R3_CLASS_DIR . 'obj.gisclient.php';
    $tools = array(
        'zoomFull' => 'zoom_full',
        'zoomPrev' => 'zoom_prev',
        'zoomNext' => 'zoom_next',
        'zoomIn' => 'zoom_in',
        'zoomOut' => 'zoom_out',
        'Pan' => 'pan',
        'dialogToPopup' => 'dialog_to_popup'
    );
    $smarty->assign('mapTools', $tools);
    $smarty->assign('mapDialog', 'dialog_list');
    $smarty->assign('gisclient_folder', 'gisclient/');
    $smarty->assign('gisclient_modules_folder', 'gisclient_modules/');
    $smarty->assign('proj4js', eco_gisclient::getProj4List());
}


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
if ($tabs !== null) {
    $smarty->assign('tabs', $tabs);
}
if ($previewMap !== null) {
    $smarty->assign('GCPreviewmap', $previewMap);
}

if ($objAction == 'show') {
    $smarty->assign('input_status', "class=\"input_readonly\" readonly");
}

$smarty->display($tpl);
