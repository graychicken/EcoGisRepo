<?php

$scriptStartTime = microtime(true);
define('R3_FAST_SESSION', true);
require_once dirname(__FILE__) . '/../../etc/config.php';
require_once R3_LIB_DIR . 'eco_app.php';
require_once R3_LIB_DIR . 'eco_utils.php';
require_once R3_LIB_DIR . 'storevar.php';
require_once R3_LIB_DIR . 'r3locale.php';
require_once R3_LANG_DIR . 'lang.php';
register_shutdown_function('shutdown');


/* ---------------- Startup ------------------------------------------------- */

R3AppStart('admin', array('auth' => true, 'auth_manager' => false, 'allow_change_password' => true));

/* ------------------------------ Settings ------------------------------ */

$url = initVar('url');
if ($url === null) {
    $page = initVar('page');
    if ($page === null) {
        $page = 'list';
    }
    $page .= '.php';
    if (count($_GET) > 0) {
        $page .= '?';
        foreach ($_GET as $key => $val) {
            if ($key != 'page') {
                if ($val == '') {
                    $page .= urlencode($key) . '&amp;';
                } else {
                    $page .= urlencode($key) . '=' . urlencode($val) . '&amp;';
                }
            }
        }
    }
    $url = $page;
}

$hasMenu = true;
$MenuItem = $auth->getConfigValue('APPLICATION', 'LOGON_ACCESS_MENU', 'building_list');

$parsedURL = parse_url($url);
if (isset($parsedURL['query'])) {
    $p = strpos($parsedURL['query'], 'menu_item');
    if ($p !== false) {
        $MenuItem = substr($parsedURL['query'], $p + 10);
    }
}

/* ------------------------------ Init Settings ------------------------------ */
require_once R3_WEB_ADMIN_DIR . 'smarty_assign.php';
require_once R3_WEB_ADMIN_DIR . 'menu.php';


/* ------------------------------ Output ------------------------------ */

$auth = R3AuthInstance::get();
$files = array();
$domainName = strtolower($auth->getDomainName());
$smarty->assign('prefetchTime', 0);
if ($auth->getConfigValue('PREFETCH', 'START_DELAY_TIME', 0) > 0) {
    $what = array('CSS' => array('fs' => R3_WEB_CSS_DIR, 'url' => R3_CSS_URL . BUILD),
        'JS' => array('fs' => R3_WEB_JS_DIR, 'url' => R3_JS_URL . BUILD),
        'IMAGE' => array('fs' => R3_WEB_IMAGES_DIR, 'url' => R3_IMAGES_URL . BUILD),
        'MAP_JS' => array('fs' => R3_WEB_MAP_DIR . 'javascript/', 'url' => R3_MAP_URL . 'javascript/' . BUILD),
        'MAP_CSS' => array('fs' => R3_WEB_MAP_DIR . 'style/', 'url' => R3_MAP_URL . 'style/' . BUILD),
        'MAP_IMAGE' => array('fs' => R3_WEB_MAP_DIR . 'mapimages/', 'url' => R3_MAP_URL . 'mapimages/' . BUILD),
        'MAP_IMAGE_BUTTON' => array('fs' => R3_WEB_MAP_DIR . 'mapimages/buttons/', 'url' => R3_MAP_URL . 'mapimages/' . BUILD . '/buttons/'),
        'MAP_IMAGE_SLIDER' => array('fs' => R3_WEB_MAP_DIR . 'mapimages/slider/', 'url' => R3_MAP_URL . 'mapimages/' . BUILD . '/slider/'),
        'MAP_LEGEND' => array('fs' => R3_OUTPUT_DIR . "{$domainName}/legend/", 'url' => R3_APP_URL . "output/{$domainName}/legend/"));

    foreach ($what as $param => $data) {
        foreach (explode(':', str_replace(array(',', ';'), ':', $auth->getConfigValue('PREFETCH', $param, ''))) as $fileMask) {
            foreach (glob($data['fs'] . basename(trim($fileMask))) as $file) {
                if (is_file($file)) {
                    //$url = $data['url'] . BUILD . '/' . substr($file, strlen($data['fs']));
                    $url = "{$data['url']}/" . substr($file, strlen($data['fs']));
                    // echo "[$url]";
                    $files[$url] = $url;
                }
            }
        }
    }
    if (count($files) > 0) {
        $smarty->assign('prefetchTime', $auth->getConfigValue('PREFETCH', 'START_DELAY_TIME', 0));
        $smarty->assign('prefetchFiles', implode("','", $files));
    }
}

if (file_exists(R3_WEB_DIR . 'version.txt')) {
    $smarty->assign('APPLICATION_VERSION', file_get_contents(R3_WEB_DIR . 'version.txt'));
}
$smarty->assign('url', $url);
$smarty->assign('lang', $lang);
$smarty->assign('lang_code', $languages[$lang]);
$smarty->display('app_manager.tpl');
