<?php

require_once(dirname(__FILE__) . '/../../etc/config.php');
require_once R3_LANG_DIR . 'lang.php';
require_once R3_LIB_DIR . 'obj.base.php';
require_once R3_LIB_DIR . 'eco_utils.php';
require_once R3_CLASS_DIR . 'obj.gisclient.php';

$auth = R3AuthInstance::get();
if (!$auth->isAuth()) {
    Header("location: logout.php?status=" . $auth->getStatusText());
    die();
}
$db = ezcDbInstance::get();

$lang = $_SESSION['lang'];
R3Locale::setLanguageID($lang);
setLang($languages[$_SESSION['lang']], LC_MESSAGES);
bindtextdomain('messages', R3_LANG_DIR);
textdomain('messages');
bind_textdomain_codeset('messages', R3_APP_CHARSET);

$_SESSION['GC_SESSION_CACHE_EXPIRE_TIMEOUT'] = time() + 10;

$_SESSION['USERNAME'] = 'admin';
$_SESSION['GROUPS'] = array('r3appusers');


$MenuItem = 'mappa';

require_once(R3_WEB_ADMIN_DIR . 'smarty_assign.php');

$toolsOptions = array();
$mapCallbacks = array();
$tools = array(
    'zoomFull' => 'zoom_full',
    'zoomPrev' => 'zoom_prev',
    'zoomNext' => 'zoom_next',
    'zoomIn' => 'zoom_in',
    'zoomOut' => 'zoom_out',
    'Pan' => 'pan',
    'measureLine' => 'measure_line',
    'measureArea' => 'measure_polygon',
    'reloadLayers' => 'reload_layers',
    'toStreetView' => 'to_street_view',
    'mapPrint' => 'print',
    'redline' => 'redline',
    'selectFromMap' => 'select',
    'toolTip' => 'tooltip',
    'unselectFeatures' => 'unselect_features',
);

function hasGeometry($sql, $id) {
    $db = ezcDbInstance::get();
    $stmt = $db->prepare($sql);
    $stmt->execute(array($id));
    return $stmt->fetchColumn();
}

function streetHasGeometry($st_id) {
    $schema = R3EcoGisHelper::getGeoSchema($_SESSION['do_id']);
    $sql = "SELECT CASE WHEN the_geom IS NULL THEN FALSE ELSE TRUE END AS has_geometry FROM {$schema}.street WHERE st_id=?";
    return hasGeometry($sql, $st_id);
}

function fractionHasGeometry($fr_id) {
    $schema = R3EcoGisHelper::getGeoSchema($_SESSION['do_id']);
    $sql = "SELECT CASE WHEN the_geom IS NULL THEN FALSE ELSE TRUE END AS has_geometry FROM {$schema}.fraction WHERE fr_id=?";
    return hasGeometry($sql, $fr_id);
}

function municipalityHasGeometry($mu_id) {
    $schema = R3EcoGisHelper::getGeoSchema($_SESSION['do_id']);
    $sql = "SELECT CASE WHEN the_geom IS NULL THEN FALSE ELSE TRUE END AS has_geometry FROM {$schema}.municipality WHERE mu_id=?";
    return hasGeometry($sql, $mu_id);
}

$ecogisDigitize = isset($_GET['ecogis_digitize']);
$ecogisCopyToTemporary = isset($_GET['ecogis_copy_to_temporary']);

$unsetZoomOn = false;
if ($ecogisCopyToTemporary) {
    // copia


    $unsetZoomOn = true;
}


if (isset($_GET['zoom_type']) && $_GET['zoom_type'] == 'zoomextent') {
    switch ($_GET['mapoper_zoom']) {
        case 'building':
            $featureType = 'g_building.building';
            $field = 'bu_id';
            $value = $_GET['mapoper_id'];
            break;
        case 'street_lighting':
            $featureType = 'g_street_lighting.street_lighting';
            $field = 'gc_id';
            $value = $_GET['mapoper_id'];
            break;
        case 'global_entry':
            $featureType = 'g_district.municipality';
            $field = 'mu_id';
            $value = $_GET['mu_id'];
            break;
        case 'mixed':
            // Get the better zoom
            if (isset($_GET['st_id']) && streetHasGeometry($_GET['st_id'])) {
                $featureType = 'g_toponimy.street';
                $field = 'st_id';
                $value = $_GET['st_id'];
            } else if (isset($_GET['fr_id']) && fractionHasGeometry($_GET['fr_id'])) {
                $featureType = 'g_toponimy.fraction';
                $field = 'fr_id';
                $value = $_GET['fr_id'];
            } else if (isset($_GET['mu_id']) && municipalityHasGeometry($_GET['mu_id'])) {
                $featureType = 'g_toponimy.municipality';
                $field = 'mu_id';
                $value = $_GET['mu_id'];
            }
            break;
        default:
            throw new Exception("Unknown mapoper_zoom parameter \"{$_GET['mapoper_zoom']}\"");
    }
    $zoomOn = array('featureType' => $featureType,
        'field' => $field,
        'value' => $value,
        'highlight' => 0);


    if ($unsetZoomOn) {
        unset($zoomOn);
    }
}

if (isset($_GET['action']) && $_GET['action'] == 'zoomon') {
    $featureType = isset($_GET['featureType']) ? $_GET['featureType'] : null;
    $field = isset($_GET['field']) ? $_GET['field'] : null;
    $value = isset($_GET['value']) ? $_GET['value'] : null;
    if ($featureType === null && isset($_GET['obj_t'])) {
        if ($_GET['obj_t'] == 'building') {
            $featureType = 'g_building.building';
        } else if ($_GET['obj_t'] == 'street_lighting') {
            $featureType = 'g_street_lighting.street_lighting';
        }
    }
    if ($field === null && isset($_GET['obj_key'])) {
        $field = $_GET['obj_key'];
    }
    if ($value === null && isset($_GET['obj_id'])) {
        $value = $_GET['obj_id'];
    }
    if ($featureType <> '' && $field <> '' && $value <> '') {
        if (!isset($_GET['highlight'])) {
            $_GET['highlight'] = 0;
        }
        $zoomOn = array(
            'featureType' => $featureType,
            'field' => $field,
            'value' => $value,
            'highlight' => $_GET['highlight']
        );
    } else if (isset($_GET['extent'])) {
        $zoomOn = array('extent' => $_GET['extent']);
    }
} else if (!empty($_GET['x']) && !empty($_GET['y']) && !empty($_GET['zoom'])) {
    $goToCenterZoom = array(
        'x' => $_GET['x'],
        'y' => $_GET['y'],
        'zoom' => $_GET['zoom']
    );
}

if (isset($_GET['tool'])) {
    switch ($_GET['tool']) {
        case 'editBuilding':
            $mapCallbacks['drawFeature'] = 'building_edit';
            $toolsOptions['drawFeature'] = "{initControls:['polygon','line',null]}";
            $tools['drawFeature'] = 'draw_feature';
            if (isset($_GET['bu_id'])) {
                $addFeatures = array(
                    'g_project.project_polygon' => array(
                        'pkey' => 'gid',
                        'table' => 'prg_polygon',
                        'ids' => array()
                    ),
                    'g_project.project_linestring' => array(
                        'pkey' => 'gid',
                        'table' => 'prg_linestring',
                        'ids' => array()
                    )
                );

                foreach ($addFeatures as $featureType => $featureData) {
                    $sql = "select gid from " . $featureData['table'] . " where bu_id=?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute(array($_GET['bu_id']));
                    while ($array = $stmt->fetch(PDO::FETCH_ASSOC))
                        array_push($addFeatures[$featureType]['ids'], $array['gid']);
                }

                $zoomOn = array(
                    'featureType' => 'g_project.project',
                    'field' => 'bu_id',
                    'value' => $_GET['bu_id'],
                    'highlight' => 0
                );
            }
            $customGisclientReadyFunctions = "gisclient.toolObjects.drawFeature._click({data:{self:gisclient.toolObjects.drawFeature}});";
            break;
        case 'editAccess':
            $mapCallbacks['selectPoint'] = 'edit_access';
            $tools['selectPoint'] = 'select_point';
            if (isset($_GET['ac_id'])) {
                $zoomOn = array(
                    'featureType' => 'g_access.access',
                    'field' => 'ac_id',
                    'value' => $_GET['ac_id'],
                    'highlight' => 0
                );
            }
            $customGisclientReadyFunctions = "gisclient.componentObjects.gcLayersManager.activateTheme('ubt_address');";
            break;
        case 'editAddress':
            $mapCallbacks['selectPoint'] = 'edit_address';
            $tools['selectPoint'] = 'select_point';
            if (isset($_GET['nc_id'])) {
                $zoomOn = array(
                    'featureType' => 'g_house_number.house_number',
                    'field' => 'nc_id',
                    'value' => $_GET['nc_id'],
                    'highlight' => 0
                );
            }
            $customGisclientReadyFunctions = "gisclient.componentObjects.gcLayersManager.activateTheme('ubt_address');";
            break;
        case 'editStreet':
            $mapCallbacks['selectBox'] = 'edit_street';
            $tools['selectBox'] = 'select_box';
            if (isset($_GET['st_id'])) {
                $zoomOn = array(
                    'featureType' => 'g_district.street',
                    'field' => 'st_id',
                    'value' => $_GET['st_id'],
                    'highlight' => 0
                );
            }
            break;
        case 'editFraction':
            $mapCallbacks['selectBox'] = 'edit_fraction';
            $tools['selectBox'] = 'select_box';
            if (isset($_GET['fr_id'])) {
                $zoomOn = array(
                    'featureType' => 'g_district.fraction',
                    'field' => 'fr_id',
                    'value' => $_GET['fr_id'],
                    'highlight' => 0
                );
            }
            break;
    }
}

$smarty->assign('lang', $lang);
$smarty->assign('lang_code', $languages[$lang]);
$smarty->assign('langKey', $languages[$lang]);

//$smarty->assign('dbtopoTools', $dbtopoTools);
//$smarty->assign('tools',$tools);
if ($ecogisDigitize) {
    $tools['ecogisDigitize'] = 'ecogis_digitize';
    $customGisclientReadyFunctions = "gisclient.toolObjects.ecogisDigitize._click({data:{self:gisclient.toolObjects.ecogisDigitize}});";
    switch ($_GET['layer']) {
        case 'building':
        case 'street_lighting':
            eco_gisclient::copyFeatureToEditTable($_GET['layer'], $_GET['mapoper_id']);
            $digitizeTarget = eco_gisclient::getDigitizeTarget($_GET['layer']);
            break;
        case 'paes':
            eco_gisclient::copyFeatureToEditTable('global_subcategory', $_GET['mapoper_id']);
            $digitizeTarget = eco_gisclient::getDigitizeTarget($_GET['layer']);
            break;
        default:
            throw new Exception('Missing or invalid layer parameter');
    }
    $smarty->assign('customDigitizeTarget', $digitizeTarget);
}


$gc = array('tools' => $tools);
$smarty->assign('gc', $gc);


$smarty->assign('proj4js', eco_gisclient::getProj4List());
if (R3_IS_MULTIDOMAIN) {
    // Read data for current domain
    $dbiniDomain = clone $dbini;
    $dbiniDomain->setDomainName(R3EcoGisHelper::getDomainCodeFromID($_SESSION['do_id']));
    $gisclientOptions = array('project' => $dbiniDomain->getValue('GISCLIENT', 'PROJECT'),
        'mapset' => $dbiniDomain->getValue('GISCLIENT', 'MAPSET'),
        'has_streeview' => $dbiniDomain->getValue('GISCLIENT', 'HAS_STREETVIEW') <> 'F',
        'streeview_options' => $dbiniDomain->getValue('GISCLIENT', 'STREETVIEW_OPTIONS', '{"correctionParameters": {"x":0, "y":0}}'),
        'has_quick_search' => $dbiniDomain->getValue('GISCLIENT', 'HAS_QUICK_SEARCH') <> 'F',
        'has_fractional_zoom' => $dbiniDomain->getValue('GISCLIENT', 'FRACTIONAL_ZOOM') <> 'F',
        'digitize_has_selection' => $dbiniDomain->getValue('GISCLIENT', 'DIGITIZE_HAS_SELECTION') <> 'F' ? 'true' : 'false',
        'do_gc_digitize_has_editing' => $dbiniDomain->getValue('GISCLIENT', 'DIGITIZE_HAS_EDITING') <> 'F' ? 'true' : 'false');
} else {
    $gisclientOptions = array('project' => $auth->getConfigValue('GISCLIENT', 'PROJECT'),
        'mapset' => $auth->getConfigValue('GISCLIENT', 'MAPSET'),
        'has_streeview' => $auth->getConfigValue('GISCLIENT', 'HAS_STREETVIEW') <> 'F',
        'streeview_options' => $auth->getConfigValue('GISCLIENT', 'STREETVIEW_OPTIONS', '{"correctionParameters": {"x":0, "y":0}}'),
        'has_quick_search' => $auth->getConfigValue('GISCLIENT', 'HAS_QUICK_SEARCH') <> 'F',
        'fractional_zoom' => $auth->getConfigValue('GISCLIENT', 'FRACTIONAL_ZOOM') <> 'F',
        'digitize_has_selection' => $auth->getConfigValue('GISCLIENT', 'DIGITIZE_HAS_SELECTION') <> 'F' ? 'true' : 'false',
        'do_gc_digitize_has_editing' => $auth->getConfigValue('GISCLIENT', 'DIGITIZE_HAS_EDITING') <> 'F' ? 'true' : 'false');
}

$smarty->assign('gisclientOptions', $gisclientOptions);

if (isset($mapCallbacks))
    $smarty->assign('mapCallbacks', $mapCallbacks);
if (isset($zoomOn))
    $smarty->assign('zoomOn', $zoomOn);
else if (isset($goToCenterZoom))
    $smarty->assign('goToCenterZoom', $goToCenterZoom);
if (isset($addFeatures))
    $smarty->assign('addFeatures', $addFeatures);
if (isset($customGisclientReadyFunctions)) {
    $smarty->assign('customGisclientReadyFunctions', $customGisclientReadyFunctions);
}
if (!empty($toolsOptions))
    $smarty->assign('toolsOptions', $toolsOptions);


$smarty->assign('gisclient_folder', 'gisclient/');
$smarty->assign('gisclient_modules_folder', 'gisclient_modules/');

$smarty->display('gisclient.tpl');
