<?php

class eco_gisclient extends R3AppBaseObject {

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $this->registerAjaxFunction('selectFeature');
        $this->registerAjaxFunction('getTemporaryFeature');
        $this->registerAjaxFunction('storeFeatureToTemporaryTable');
    }

    public function getPageTitle() {
        
    }

    public function getListSQL() {
        
    }

    public function createListTableHeader(&$order) {
        
    }

    public function getData($id = null) {
        
    }

    /**
     * Copy feature to temporary table (building, street lighting, paes)
     */
    static public function copyFeatureToEditTable($featureName, $featureId) {
        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();

        $sessionId = session_id();
        $schemaName = R3_DB_SCHEMA;
        $uid = $auth->getUID();

        switch ($featureName) {
            case 'building':
                $tableName = $featureName;
                $tablePKey = 'bu_id';
                break;
            case 'street_lighting':
                $tableName = $featureName;
                $tablePKey = 'sl_id';
                break;
            case 'global_subcategory':
                $tableName = $featureName;
                $tablePKey = 'gs_id';
                break;

            default:
                throw new Exception("Unknown featureName \"{$featureName}\"");
        }
        $sql = "DELETE FROM edit_tmp_polygon WHERE session_id=?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array(session_id()));

        $sql = "INSERT INTO edit_tmp_polygon
              (session_id, org_schema, org_table, org_id, gorder, user_id, mod_date, the_geom)
              SELECT '{$sessionId}', '{$schemaName}', '{$tableName}', $tablePKey, generate_series(1, numgeometries(the_geom)), {$uid}, CURRENT_TIMESTAMP, geometryN(the_geom, generate_series(1, numgeometries(the_geom)))
              FROM {$tableName}
              WHERE {$tablePKey}=" . (int) $featureId;
        $db->exec($sql);
    }

    /**
     * Return the available digitalized target
     */
    static public function getDigitizeTarget($layer) {

        switch ($layer) {
            case 'building';
                return array(array('key' => 'building', 'val' => _('Edifici')));
            case 'street_lighting';
                return array(array('key' => 'street_lighting', 'val' => _('Tratti i strada')));
            case 'paes';
                return array(array('key' => 'building', 'val' => _('Edifici')),
                    array('key' => 'street_lighting', 'val' => _('Tratti i strada')));
            default:
                throw new Exception("Unknown layer \"{$layer}\"");
        }
    }

    static function getProj4List() {
        $db = ezcDbInstance::get();
        $q = $db->createSelectQuery();
        $q->select("srid, proj4text")
                ->from("ecogis.customer cus")
                ->leftJoin('public.spatial_ref_sys srs', 'cus.cus_srid', 'srs.srid')
                ->where('do_id=?');
        $stmt = $q->prepare();
        $stmt->execute(array($_SESSION['do_id']));
        $proj4js = $stmt->fetch(PDO::FETCH_ASSOC);
        return $proj4js;
    }

    /**
     * 
     * @param array $request    the request
     * @return array            the result data
     */
    public function selectFeature($request) {
        $db = ezcDbInstance::get();
        $auth = $this->auth;

        switch ($request['target']) {
            case 'building':
            case 'street_lighting':
                $tableDef = $auth->getConfigValue('APPLICATION', strtoupper($request['target']) . '_TABLE', array());
                break;
            default:
                throw new Exception("Invalid target \"{$request['target']}\"");
        }

        // Get srid
        $sql = "SELECT ST_srid(the_geom) FROM {$tableDef['table']} WHERE the_geom IS NOT NULL LIMIT 1";
        $srid = $db->query($sql)->fetchColumn();
        if (empty($srid)) {
            throw new Exception("No geometry in table {$tableDef['table']}");
        }

        $buffer = isset($tableDef['buffer']) ? $tableDef['buffer'] : 0;
        $tollerance = isset($tableDef['tollerance']) ? $tableDef['tollerance'] : $buffer;
        $minTollerance = max($tollerance, 1);
        $geometryFromText = "ST_GeometryFromText(?, {$srid})";

        $srid = $db->query($sql)->fetchColumn();

        $q = $db->createSelectQuery();
        $q->select("{$tableDef['pkey']} AS gid, ST_AsText({$tableDef['the_geom']}) AS txt_geom")
                ->from($tableDef['table'])
                ->where(array("{$tableDef['the_geom']} && ST_Buffer({$geometryFromText}, {$minTollerance})",
                    "ST_Distance(SetSrid({$tableDef['the_geom']}, {$srid}), {$geometryFromText}) <= {$tollerance}"));
        $stmt = $q->prepare();
        $stmt->execute(array($request['point'], $request['point']));
        $result = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = array('id' => $row['gid'], 'type' => _($request['target']), 'geom' => $row['txt_geom']);
        }
        return array(
            'status' => R3_AJAX_NO_ERROR,
            'data' => $result);
    }

    /**
     * 
     * @param array $request    the request
     * @return array            the result data
     */
    public function getTemporaryFeature($request) {

        R3EcoGisHelper::cleanTmporaryMapEditingData();

        $db = ezcDbInstance::get();

        $q = $db->createSelectQuery();
        $q->select("ST_AsText(the_geom) AS txt_geom")
                ->from("edit_tmp_polygon")
                ->where(array("session_id=?"));
        $stmt = $q->prepare();
        $stmt->execute(array(session_id()));
        $result = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[] = array('type' => _(''), 'geom' => $row['txt_geom']);
        }
        return array(
            'status' => R3_AJAX_NO_ERROR,
            'data' => $result);
    }

    public function storeFeatureToTemporaryTable($request) {
        $auth = R3AuthInstance::get();
        $db = ezcDbInstance::get();

        switch ($request['layer']) {
            case 'building':
                $table = 'edit_tmp_polygon';
                break;
            case 'street_lighting':
                $table = 'edit_tmp_polygon';
                break;

            default:
                throw new Exception("Invalid later \"{$request['layer']}\"");
        }

        $sql = "DELETE FROM {$table} WHERE session_id=?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array(session_id()));

        $seq = 0;
        $sessionId = session_id();
        $uid = $auth->getUID();
        foreach ($request['data'] as $geometry) {
            $seq++;
            $the_geom = 'ST_MULTI(?)';
            $sql = "INSERT INTO {$table} (session_id, gorder, user_id, the_geom)
                    SELECT '{$sessionId}', {$seq}, {$uid}, (ST_DUMP(the_geom)).geom 
                    FROM ST_MULTI(ST_BUFFER(ST_GeomFromText(?), 0.1)) the_geom";
            $stmt = $db->prepare($sql);
            $stmt->execute(array($geometry));
        }
        return array('status' => R3_AJAX_NO_ERROR);
    }

    public function checkPerm() {
        return true;
    }

}
