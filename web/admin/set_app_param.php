<?php  /* UTF-8 FILE: àèìòù */
$scriptStartTime = microtime(true);
require_once dirname(__FILE__) .  '/../../etc/config.php';
require_once R3_LIB_DIR . 'eco_app.php';
require_once R3_LIB_DIR . 'storevar.php';
register_shutdown_function('shutdown');

/* ---------------- Startup ------------------------------------------------- */

R3AppStart('admin');


/* ---------------- Settings ------------------------------------------------ */

$kind =           initVar('kind');
$global_domain =  initVar('global_domain');

function resetMapData() {
    $keys = array('gLanguage', 'allGroups', 'defGroups', 'grouplist', 'mapoper', 'mapoper_zoom', 'mapoper_id', 'bbox', 
                 'map_zoom_type', 'map_zoom_extent', 'GEOEXT', 'OLDGEOEXT', 'groups', 'geo_scale');
    
    foreach($keys as $key) {
        if (array_key_exists($key, $_SESSION)) {
            unset($_SESSION[$key]);
        }
    }
}

switch($kind) {
    case 'domain':
        if ($auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
            $_SESSION['do_id'] = $global_domain;
            resetMapData();
        }
    break;
    case 'lang':
        $lang =       strToLower(initVar('lang'));
        if (is_numeric($lang)) {
            if (!array_key_exists($lang, $languages)) {
                $lang = false;
            }
        } else {
            $lang = array_search($lang, $languages);
        }
        if ($lang === false) {
            reset($languages);
            $lang = key($languages);
        }
        $_SESSION['lang'] = $lang;
        $auth->setParam('us_lang', $lang, true);
    break;
}

Header("Location: " . $auth->getConfigValue('APPLICATION', 'LOGON_ACCESS_POINT', 'main.php'));

