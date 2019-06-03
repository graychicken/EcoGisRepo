<?php

require_once dirname(__FILE__) . '/../../../etc/config.php';
require_once R3_LANG_DIR . 'lang.php';

R3AppStart();

if (!$auth->isAuth()) {
    Header("location: logout.php?status=" . $auth->getStatusText());
    die();
}

define('PREVIEWMAP_DEBUG', 1);

require_once R3_LIB_DIR . 'r3ogcmap.php';
require_once R3_LIB_DIR . 'r3ogcmap/previewMap.php';

$db = ezcDbInstance::get();

$self = $_SERVER['PHP_SELF'];
$pos = strrpos($self, '/') + 1;
$selfDir = substr($self, 0, $pos);
$request = $_SERVER['REQUEST_URI'];
$path = str_replace($selfDir, '', $request);

$parts = explode('/', $path);

switch ($parts[0]) {
    case 'previewmap':

        $table = $parts[1];
        $idField = $parts[2];
        $idValue = $parts[3];
        $pos = strpos($parts[4], '?');
        if ($pos !== false) {
            $fileDimensions = substr($parts[4], 0, $pos);
            $queryString = trim(substr($parts[4], $pos), '?');
            if (!empty($queryString)) {
                $queryParameters = null;
                parse_str($queryString, $queryParameters);
            }
        } else {
            $fileDimensions = $parts[4];
        }

        $layergroupHighlight = null;
        if ($table == 'tmp') {
            $enableCache = false;
            $layergroupHighlight = 'g_tmp';
            $idValue = session_id();
        } else {
            $enableCache = true;
            $layergroupHighlight = "g_{$table}";
        }


        $fileId = $idValue . '_' . $fileDimensions;

        // set cache attributes
        $cacheAttributes = array(
            'layer' => $table,
            'lang' => $languages[$lang],
            'id' => $idValue
        );

        $cadastreIntersection = false;
        $bufferExtent = false;

        ezcCacheManager::createCache('mappreview', R3_CACHE_DIR . 'mappreview/', 'ezcCacheStorageFilePlain', array('ttl' => 5 * 24 * 60 * 60));
        $cache = ezcCacheManager::getCache('mappreview');

        if (!$enableCache || ($imageContent = $cache->restore($fileId, $cacheAttributes)) === false) {
            list($size, $split) = explode('-', $fileDimensions);
            list($x, $y) = explode('x', $size);
            $size = array((int) $x, (int) $y);
            list($unitDimensions, $format) = explode('.', $split);
            $unitDimensions = explode('x', $unitDimensions);

            try {

                switch ($table) {
                    case 'foto':
                        $tableName = 'document_data';
                        break;
                    default:
                        $tableName = $table;
                        break;
                }

                $previewOptions = array(
                    'size' => $size,
                    'unit_dimensions' => $unitDimensions,
                    'table' => $tableName,
                    'id_field' => $idField,
                    'id_value' => $idValue,
                    'db' => $db,
                    'singleRequest' => false,
                    'mergeSLD' => false,
                    'force_contains_object' => true
                );

                if (R3_IS_MULTIDOMAIN) {
                    // Prende il mapset del dominio corrente
                    $mapset = $auth->getConfigValueFor($auth->getDomainCodeFromID($_SESSION['do_id']), APPLICATION_CODE, null, 'GISCLIENT', 'MAPSET', 'a');
                } else {
                    $mapset = $auth->getConfigValue('GISCLIENT', 'MAPSET');
                }

                $initMapUrl = GC_INITMAP_URL . '&mapset=' . $mapset;
                $preview = new R3PreviewMap($initMapUrl, $previewOptions);
                $preview->setGlobalWmsParameters(array('lang' => $languages[$lang]));

                $preview->includeWms($layergroupHighlight);

                $preview->setSldFolderUrl(GC_PREVIEWMAP_SLD_FOLDER_URL);

                if ($table == 'tmp') {
                    $table = 'ecogis.edit_tmp_polygon';
                }

                $mbr = null;
                $sql = <<<EOQ
SELECT
     ST_XMin(st_extent(the_geom)) - 10 as minx,
     ST_YMin(st_extent(the_geom)) - 10 as miny,
     ST_XMax(st_extent(the_geom)) + 10 as maxx,
     ST_YMax(st_extent(the_geom)) + 10 as maxy
FROM {$table}
WHERE {$idField} = :id
                    
EOQ;
                $db_res = $db->prepare($sql);
                $db_res->execute(array('id' => $idValue == '' ? null : $idValue));
                $mbr = $db_res->fetch(PDO::FETCH_NUM);
                if (empty($mbr) || empty($mbr[0])) {
                    header("Content-Type: image/png");
                    echo file_get_contents('../../images/blank.png');
                    die();
                }
                session_write_close();
                $imageContent = $preview->generatePreviewMap($mbr, $format, $layergroupHighlight);
            } catch (Exception $e) {
                die($e);
            }
            if ($enableCache) {
                $cache->store($fileId, $imageContent, $cacheAttributes);
            }
        }

        header("Content-Type: image/png");
        echo $imageContent;
        break;

    default:
        break;
}

function initGeomObject($objectType, $id) {
    global $lang;
    if (!R3GeomObject::isGeometry($objectType)) {
        die("Not a geometry object");
    }

    $objClass = R3GeomObject::getClass($objectType) . 'Geom';

    $gObj = new $objClass($_SESSION['dom_id'], $lang);
    $gObj->setId($id);
    return $gObj;
}
