<?php

define('DATABASE_VERSION', '1.29');

//require_once R3_LIB_DIR . 'r3mdb2.php';
require_once R3_LIB_DIR . 'r3dbcatalog.php';
require_once R3_LIB_DIR . 'log_table.php';
require_once R3_LIB_DIR . 'view_def.php';

class R3EcoGisCustomerLogTriggerDef {

    static private $tables = array('building' => 'bu_id',
        'building_build_year' => 'bby_id',
        'building_purpose_use' => 'bpu_id',
        'stat_building_purpose_use' => 'sbpu_id',
        'building_restructure_year' => 'bry_id',
        'building_type' => 'bt_id',
        'cat_munic' => 'cm_id',
        'consumption' => 'co_id',
        'customer' => 'do_id', // Corretto
        'device' => 'dev_id',
        'device_type' => 'dt_id',
        'document' => 'doc_id',
        'document_type' => 'doct_id',
        'energy_class' => 'ec_id',
        'energy_class_limit' => 'ecl_id',
        'energy_meter' => 'em_id',
        'energy_meter_object' => 'emo_id',
        'energy_meter_production_type' => 'empt_id',
        'energy_source' => 'es_id',
        'energy_source_udm' => 'esu_id',
        'energy_type' => 'et_id',
        'energy_zone' => 'ez_id',
        'funding_type' => 'ft_id',
        'global_strategy' => 'gst_id',
        'global_entry' => 'ge_id',
        'global_plain' => 'gp_id',
        'global_energy_type' => 'get_id',
        'global_energy_source' => 'ges_id',
        'global_category' => 'gc_id',
        'global_type' => 'gt_id',
        'global_plain_row' => 'gpr_id',
        'global_plain_action' => 'gpa_id',
        'global_plain_action_category' => 'gpac_id',
        'global_method' => 'gm_id',
        'stat_general' => 'sg_id',
        'stat_type' => 'st_id',
        'funding_type' => 'ft_id',
        'municipality' => 'mu_id',
        'simulation_work' => 'sw_id',
        'street_lighting' => 'sl_id',
        'udm' => 'udm_id',
        'utility_product' => 'up_id',
        'utility_supplier' => 'us_id',
        'work' => 'wo_id',
        'work_status' => 'ws_id',
        'work_type' => 'wt_id',
        'action_catalog' => 'ac_id',
        'simulation_work_detail' => 'swd_id',
        'import' => 'im_id',
        'global_plain_gauge_udm' => 'gpgu_id',
        'global_plain_gauge' => 'gpg_id',
        'global_plain_monitor' => 'gpm_id',
        'common.street' => 'st_id',
        'common.fraction' => 'fr_id');

    static public function getTables() {
        return R3EcoGisCustomerLogTriggerDef::$tables;
    }

}

/**
 * Utility function class for EcoGIS
 */
class R3EcoGisCustomerHelper {

    private static $images = array('do_login_logo_dx' => 'login_dx', 'do_login_logo_sx' => 'login_sx',
        'do_app_logo_dx' => 'logo_dx', 'do_app_logo_sx' => 'logo_sx',
        'do_map_logo_dx' => 'map_dx', 'do_map_logo_sx' => 'map_sx',
        'do_print_logo_1' => 'print_logo_1', 'do_print_logo_2' => 'print_logo_2');
    private static $css = array('do_app_css' => 'custom', 'do_public_css' => 'public', 'do_map_css' => 'map');

    /**
     * Return the domain list
     *
     * return array                     the domain list
     */
    static public function getDomainsList($auth) {
        $result = array();
        $result[''] = _('-- Nessuno --');
        foreach ($auth->getDomainsList() as $val)
            $result[$val['dn_name']] = $val['dn_name'];
        return $result;
    }

    static public function getGroupsList($auth) {
        $result = array();
        $result[''] = _('-- Nessuno --');
        foreach ($auth->getGroupsList() as $val)
            $result[$val['gr_name']] = $val['gr_name'];
        return $result;
    }

    static public function getSRIDDesc($srid) {
        $db = ezcDbInstance::get();
        $sql = "SELECT srtext FROM spatial_ref_sys WHERE srid=" . (int) $srid;
        $a = explode('"', $db->query($sql)->fetchColumn());
        if (count($a) <= 1)
            return '';
        return $a[1];
    }

    static public function getAvailableMunicipalityList(array $opt = array()) {
        $opt = array_merge(array('pr_id' => '', 'mu_name' => ''), $opt);
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $constraints = array();
        $constraints[] = 'do_id IS NULL';
        $limit = 1000;  // Numero massimo di comuni mostrati
        if ($opt['pr_id'] > 0) {
            $constraints[] = 'pr_id=' . (int) $opt['pr_id'];
            $limit = null;
        }
        if ($opt['mu_name'] <> '') {
            $constraints[] = "mu_name_$lang ILIKE " . $db->quote('%' . $opt['mu_name'] . '%');
            $limit = null;
        }
        return R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_prov_' . R3Locale::getLanguageID(), array('constraints' => $constraints, 'limit' => $limit));
    }

    static public function getSelectedMunicipalityList($do_id, array $opt = array()) {
        $opt = array_merge(array('pr_id' => '', 'mu_name' => ''), $opt);
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $constraints = array();
        $constraints[] = 'do_id=' . (int) $do_id;
        if ($opt['pr_id'] > 0)
            $constraints[] = 'pr_id=' . (int) $opt['pr_id'];
        if ($opt['mu_name'] <> '')
            $constraints[] = "mu_name_$lang ILIKE " . $db->quote('%' . $opt['mu_name'] . '%');
        return R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_prov_' . R3Locale::getLanguageID(), array('constraints' => $constraints));
    }

    /**
     * Create a database user (if not exists), or replace its password if not empty
     */
    static public function createDBUser($login, $password) {
        //global $dsn, $mdb2;
        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::createDBUser({$login}, ***)", ezcLog::DEBUG);

        $db = ezcDbInstance::get();
        $catalog = R3DbCatalog::factory($db);
        if (!$catalog->userExists($login)) {
            $sql = "CREATE USER $login PASSWORD '$password'";
            $db->exec($sql);
        } else if ($password <> '') {
            // Replace password
            $sql = "ALTER USER $login PASSWORD '$password'";
            $db->exec($sql);
        }
        try {
            $db->exec("COMMENT ON USER $login IS 'Ecogis user'");
        } catch (Exception $e) {
            // Fail if user is not super-user.
        }
    }

    /**
     * Drop a database user
     */
    static public function dropDBUser($login) {
        //global $mdb2;
        $db = ezcDbInstance::get();
        $catalog = R3DbCatalog::factory($db);
        if ($catalog->userExists($login)) {
            $sql = "DROP USER $login";
            $db->exec($sql);
        }
    }

    /**
     * Create a schema (if not exists), and grant its access to $user
     */
    static public function createSchema($name, $accessUser = '') {
        //global $mdb2;
        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::createSchema(\"{$name}\")", ezcLog::DEBUG);

        $db = ezcDbInstance::get();
        $catalog = R3DbCatalog::factory($db);
        if (!$catalog->schemaExists($name)) {
            $catalog->createSchema($name);
        }
        // Grant usage
        $db = ezcDbInstance::get();
        if ($catalog->isSuperUser()) {
            $sql = "ALTER SCHEMA {$name} OWNER TO {$mdb2->dsn['username']}";
            $db->exec($sql);
        }
        $sql = "GRANT USAGE ON SCHEMA {$name} TO {$accessUser}";
        $db->exec($sql);
    }

    /**
     * Drop a schema
     */
    static public function dropSchema($name) {
        //global $mdb2;
        $db = ezcDbInstance::get();
        $catalog = R3DbCatalog::factory($db);
        if ($catalog->schemaExists($name)) {
            $sql = "DROP SCHEMA $name";
            $db->exec($sql);
        }
    }

    /**
     * Create a domain user (if not exists)
     */
    static public function createDomain($dn_name, $dn_name_alias = '') {
        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::createDomain(\"{$dn_name}\")", ezcLog::DEBUG);

        $auth = R3AuthInstance::get();
        $do_names = array($dn_name);
        if ($dn_name_alias <> '') {
            $dn_name_alias = str_replace(';', ',', $dn_name_alias);
            foreach (explode(',', $dn_name_alias) as $alias)
                $do_names[] = $alias;
        }
        $do_auth_type = 'DB';
        $do_auth_data = '';
        $applications = array(APPLICATION_CODE => APPLICATION_CODE);
        if (in_array($dn_name, R3EcoGisCustomerHelper::getDomainsList($auth))) {
            // Add the others applications
            foreach ($auth->getApplicationsList() as $app) {
                $applications[$app['app_code']] = $app['app_code'];
            }
            $auth->modDomain($dn_name, $do_names, $do_auth_type, $do_auth_data, array_values($applications));
        } else {
            $auth->addDomain($do_names, $do_auth_type, $do_auth_data, $applications);
        }
        $data = $auth->getDomainData($dn_name, false, true);
        return $data['do_id'];
    }

    /**
     * Create a domain user (if not exists)
     */
    static public function deleteDomainLogs($do_id) {
        $db = ezcDbInstance::get();

        $sql = "DELETE FROM auth.logs WHERE do_id={$do_id}";
        $db->exec($sql);
    }

    static public function deleteDomainSettings($do_id) {
        $db = ezcDbInstance::get();

        $sql = "DELETE FROM auth.settings WHERE do_id={$do_id}";
        $db->exec($sql);
    }

    static public function deleteDomainUsers($do_id) {
        $db = ezcDbInstance::get();

        $sql = "DELETE FROM auth.users WHERE do_id={$do_id}";
        $db->exec($sql);
    }

    static public function deleteDomainEnergySource($do_id) {
        $db = ezcDbInstance::get();

        $sql = "DELETE FROM ecogis.energy_source_udm WHERE do_id={$do_id}";
        $db->exec($sql);
    }

    /**
     * Create a domain user (if not exists)
     */
    static public function dropDomain($dn_name) {
        $auth = R3AuthInstance::get();
        $auth->delDomain($dn_name);
    }

    static public function createCustomer($do_id, $data) {
        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::createCustomer({$do_id}, \"{$data['cus_name_1']}\")", ezcLog::DEBUG);

        $db = ezcDbInstance::get();
        $isUpdate = $db->query("SELECT COUNT(*) FROM customer WHERE do_id=" . (int) $do_id)->fetchColumn();
        if ($isUpdate) {
            $sql = "UPDATE customer SET " .
                    "  cus_name_1=" . $db->quote($data['cus_name_1']) . ", " .
                    "  cus_name_2=" . $db->quote($data['cus_name_2']) . ", " .
                    "  cus_srid=" . $db->quote($data['cus_srid']) . " " .
                    "WHERE do_id=" . (int) $do_id;
        } else {
            $sql = "INSERT INTO customer (do_id, cus_name_1, cus_name_2, cus_srid) " .
                    "VALUES ($do_id, " . $db->quote($data['cus_name_1']) . ", " . $db->quote($data['cus_name_2']) . ", " .
                    (int) $data['cus_srid'] . ")";
        }
        $db->exec($sql);
    }

    static public function deleteCustomer($do_id) {
        $db = ezcDbInstance::get();
        $sql = "DELETE FROM customer WHERE do_id=" . (int) $do_id;
        $db->exec($sql);
    }

    // Aggiunge un utente alla gestione utenti
    static public function createUser($do_id, $data) {
        if (isset($data['us_login']) && $data['us_login'] <> '') {
            ezcLog::getInstance()->log("R3EcoGisCustomerHelper::createUser({$do_id}, {$data['us_login']})", ezcLog::DEBUG);
            $auth = R3AuthInstance::get();
            if (!$auth->getUserData($data['dn_name'], APPLICATION_CODE, $data['us_login'])) {
                $auth->addUserFromArray($data['dn_name'], $data['us_login'], array('us_name' => $data['us_name'],
                    'us_password' => $data['us_password'],
                    'us_status' => 'ENABLED',
                    'groups' => array(array('app_code' => APPLICATION_CODE, 'gr_name' => $data['us_group'])),
                    'us_pw_expire' => null,
                    'us_pw_expire_alert' => null,
                    'us_start_date' => null,
                    'us_expire_date' => null,
                    'perms' => array(),
                    'ip' => array(),
                    'forceChangePassword' => false));
            }
            return true;
        }
        return false;
    }

    /**
     * Create a domain user (if not exists)
     */
    static public function createViews($schema, $accessUser, $srid, $do_id, $opt) {
        //global $mdb2;
        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::createViews(\"{$schema}\", \"{$accessUser}\", {$srid}, {$do_id})", ezcLog::DEBUG);

        $municipalityTot = count(R3EcoGisCustomerHelper::getSelectedMunicipalityList($do_id));

        $viewDef = R3EcoGisCustomerViewDef::getViewsDef();
        if ($municipalityTot > 1) {
            $viewDef = array_merge($viewDef, R3EcoGisCustomerViewDef::getMultiMunicipalityViewsDef());
        } else {
            $viewDef = array_merge($viewDef, R3EcoGisCustomerViewDef::getSingleMunicipalityViewsDef());
        }
        $db = ezcDbInstance::get();
        $catalog = R3DbCatalog::factory($db);
        $hasGeometryColumn = false;

        $db->beginTransaction();
        foreach ($viewDef as $name => $def) {
            $ppos = strpos($name, '.');
            if ($ppos === false) {
                $viewSchema = $schema;
            } else {
                $viewSchema = substr($name, 0, $ppos);
                $viewSchema = str_replace(array('<ECOGIS-SCHEMA>',
                    '<COMMON-SCHEMA>',
                    '<DOMAIN-ID>',
                    '<SRID>'), array(R3_DB_SCHEMA,
                    R3_COMMON_SCHEMA,
                    $do_id,
                    $srid), $viewSchema);
                $name = substr($name, $ppos + 1);
            }

            if (isset($def['dependency_view'])) {
                foreach ($def['dependency_view'] as $depViewName) {
                    if (!isset($viewDef[$depViewName])) {
                        throw new Exception("Dependency view \"{$depViewName}\" not defined in view_def.php");
                    }
                    $depViewName = str_replace(array('<ECOGIS-SCHEMA>',
                        '<COMMON-SCHEMA>',
                        '<DOMAIN-ID>',
                        '<SRID>'), array(R3_DB_SCHEMA,
                        R3_COMMON_SCHEMA,
                        $do_id,
                        $srid), $depViewName);
                    $sql = "DROP VIEW IF EXISTS {$depViewName}";
                    $db->exec($sql);
                }
            }

            if ($catalog->viewExists("{$viewSchema}.{$name}")) {
                $sql = "DROP VIEW IF EXISTS {$viewSchema}.{$name}";
                $db->exec($sql);
            }
            $sql = "CREATE VIEW {$viewSchema}.{$name} AS " .
                    str_replace(array('<ECOGIS-SCHEMA>',
                        '<COMMON-SCHEMA>',
                        '<DOMAIN-ID>',
                        '<SRID>',
                        '<CONSUMPTION-SEQUENCE-DURATION>'), array(R3_DB_SCHEMA,
                        R3_COMMON_SCHEMA,
                        $do_id,
                        $srid,
                        $opt['totYears']), $def['sql']);
            $db->exec($sql);
            if ($catalog->isSuperUser()) {
                $sql = "ALTER TABLE {$viewSchema}.{$name} OWNER TO {$mdb2->dsn['username']}";
                $db->exec($sql);
            }
            $db->exec("GRANT SELECT ON TABLE {$viewSchema}.{$name} TO {$accessUser}");
            $db->exec("GRANT SELECT ON TABLE {$viewSchema}.{$name} TO mapserver");
            if (isset($def['geo'])) {
                $sql = "DELETE FROM geometry_columns " .
                        "WHERE f_table_catalog='' AND " .
                        "      f_table_schema='{$viewSchema}' AND " .
                        "      f_table_name='{$name}' AND " .
                        "      f_geometry_column='{$def['geo']['col']}'";
                $db->exec($sql);
                $sql = "INSERT INTO geometry_columns " .
                        "VALUES ('', '{$viewSchema}', '{$name}', '{$def['geo']['col']}', {$def['geo']['dim']}, $srid, '{$def['geo']['type']}')";
                $db->exec($sql);
                $hasGeometryColumn = true;
            }
            if (isset($def['desc'])) {
                $sql = "COMMENT ON VIEW {$viewSchema}.{$name} IS " . $db->quote($def['desc']);
                $db->exec($sql);
            }
            if ($hasGeometryColumn) {
                $db->exec("GRANT SELECT ON TABLE public.geometry_columns TO $accessUser");
            }
        }
        $db->commit();
    }

    /**
     * Create a domain user (if not exists)
     */
    static public function dropViews($schema) {
        //global $mdb2;

        $viewDef = array_merge(R3EcoGisCustomerViewDef::getViewsDef(), R3EcoGisCustomerViewDef::getMultiMunicipalityViewsDef(), R3EcoGisCustomerViewDef::getSingleMunicipalityViewsDef());
        $db = ezcDbInstance::get();
        $catalog = R3DbCatalog::factory($db);

        foreach ($viewDef as $name => $def) {
            if ($catalog->viewExists("{$schema}.{$name}")) {
                $sql = "DROP VIEW {$schema}.{$name}";
                $db->exec($sql);
                if (isset($def['geo'])) {
                    $sql = "DELETE FROM geometry_columns " .
                            "WHERE f_table_catalog='' AND " .
                            "      f_table_schema='$schema' AND " .
                            "      f_table_name='{$name}' ";
                    $db->exec($sql);
                }
            }
        }
    }

    /**
     * Create trigget to log record change user/time (but not the old record data)
     */
    static public function setLoggerTrigger() {
        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::setLoggerTrigger()", ezcLog::DEBUG);

        $tables = R3EcoGisCustomerLogTriggerDef::getTables();
        $db = ezcDbInstance::get();
        $db->beginTransaction();
        foreach ($tables as $table => $pk) {
            $tbl = explode('.', $table);
            if (count($tbl) == 2) {
                $table = array('schema' => $tbl[0], 'table' => $tbl[1]);
            } else {
                $table = array('schema' => R3_DB_SCHEMA, 'table' => $tbl[0]);
            }
            $sql = "DROP TRIGGER IF EXISTS {$table['table']}_update_trigger ON {$table['schema']}.{$table['table']}";
            $db->exec($sql);
            $sql = "CREATE TRIGGER {$table['table']}_update_trigger AFTER INSERT OR UPDATE OR DELETE ON {$table['schema']}.{$table['table']} FOR EACH ROW EXECUTE PROCEDURE change_logger('{$pk}')";
            $db->exec($sql);
            if ($db->query("SELECT ecogis.get_log_table('{$table['schema']}', '{$table['table']}')")->fetchColumn() == null) {
                $sql = "SELECT ecogis.add_log_table('{$table['schema']}', '{$table['table']}')";
                $db->exec($sql);
            }
        }
        $db->commit();
    }

    // Grant select to all the user table
    static public function grantTables($schema, $user) {
        //global $mdb2;

        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::grantTables(\"{$schema}\", \"{$user}\")", ezcLog::DEBUG);
        $db = ezcDbInstance::get();
        $catalog = R3DbCatalog::factory($db);
        foreach ($catalog->getTableList(array('schema' => $schema)) as $table) {
            if ($catalog->isSuperUser()) {
                $sql = "ALTER TABLE {$table['schema']}.{$table['table']} OWNER TO {$mdb2->dsn['username']}";
                $db->exec($sql);
                $db->exec("GRANT SELECT ON TABLE {$table['schema']}.{$table['table']} TO {$user}");
            }
        }
        $db->exec("GRANT USAGE ON SCHEMA ecogis TO $user");
        $db->exec("GRANT SELECT ON TABLE ecogis.edit_tmp_point TO $user");
        $db->exec("GRANT SELECT ON TABLE ecogis.edit_tmp_linestring TO $user");
        $db->exec("GRANT SELECT ON TABLE ecogis.edit_tmp_polygon TO $user");
    }

    // (re)Populate the geometry columns
    static public function populateGeometryColumns($schema) {
        //global $mdb2;

        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::populateGeometryColumns(\"{$schema}\")", ezcLog::DEBUG);

        $db = ezcDbInstance::get();
        $catalog = R3DbCatalog::factory($db);
        $geoTables = array();
        if ($schema == R3_DB_SCHEMA) {
            // Common tables
            $geoTables[] = array('schema' => 'common', 'table' => 'fraction', 'column' => 'the_geom', 'dim' => 2, 'srid' => -1, 'type' => 'POLYGON');
            $geoTables[] = array('schema' => 'common', 'table' => 'street', 'column' => 'the_geom', 'dim' => 2, 'srid' => -1, 'type' => 'POLYGON');
            // Ecogis tables
            $geoTables[] = array('schema' => R3_DB_SCHEMA, 'table' => 'action_catalog', 'column' => 'the_geom', 'dim' => 2, 'srid' => -1, 'type' => 'MULTIPOLYGON');
            $geoTables[] = array('schema' => R3_DB_SCHEMA, 'table' => 'building', 'column' => 'the_geom', 'dim' => 2, 'srid' => -1, 'type' => 'MULTIPOLYGON');
            $geoTables[] = array('schema' => R3_DB_SCHEMA, 'table' => 'global_subcategory', 'column' => 'the_geom', 'dim' => 2, 'srid' => -1, 'type' => 'MULTIPOLYGON');
            $geoTables[] = array('schema' => R3_DB_SCHEMA, 'table' => 'street_lighting', 'column' => 'the_geom', 'dim' => 2, 'srid' => -1, 'type' => 'MULTIPOLYGON');
            $geoTables[] = array('schema' => R3_DB_SCHEMA, 'table' => 'cache', 'column' => 'the_geom', 'dim' => 2, 'srid' => -1, 'type' => 'POLYGON');
            // Municipality, province, region table (SRID=23032)
            $geoTables[] = array('schema' => R3_DB_SCHEMA, 'table' => 'municipality', 'column' => 'the_geom', 'dim' => 2, 'srid' => 23032, 'type' => 'MULTIPOLYGON');
            $geoTables[] = array('schema' => R3_DB_SCHEMA, 'table' => 'province', 'column' => 'the_geom', 'dim' => 2, 'srid' => 23032, 'type' => 'MULTIPOLYGON');
            $geoTables[] = array('schema' => R3_DB_SCHEMA, 'table' => 'region', 'column' => 'the_geom', 'dim' => 2, 'srid' => 23032, 'type' => 'MULTIPOLYGON');
            // Editing tables
            $geoTables[] = array('schema' => R3_DB_SCHEMA, 'table' => 'edit_tmp_point', 'column' => 'the_geom', 'dim' => 2, 'srid' => -1, 'type' => 'POINT');
            $geoTables[] = array('schema' => R3_DB_SCHEMA, 'table' => 'edit_tmp_linestring', 'column' => 'the_geom', 'dim' => 2, 'srid' => -1, 'type' => 'LINESTRING');
            $geoTables[] = array('schema' => R3_DB_SCHEMA, 'table' => 'edit_tmp_polygon', 'column' => 'the_geom', 'dim' => 2, 'srid' => -1, 'type' => 'POLYGON');
        } else {
            $tables = array_merge($catalog->getTableList(array('schema' => $schema)), $catalog->getViewList(array('schema' => $schema)));
            foreach ($tables as $table) {
                ezcLog::getInstance()->log("R3EcoGisCustomerHelper::populateGeometryColumns(): Getting table info for table {$schema}.{$table['table']}", ezcLog::DEBUG);
                foreach ($catalog->getTableDesc("{$schema}.{$table['table']}") as $field) {
                    if ($field['column_name'] == 'the_geom') {
                        //SS : WARNING! Non modificare  geometrytype  in st_geometrytype!!!
                        $data = $db->query("SELECT DISTINCT st_ndims(the_geom) AS ndims, st_srid(the_geom) AS srid, geometrytype(the_geom) AS type FROM {$schema}.{$table['table']} WHERE the_geom IS NOT NULL")->fetch(PDO::FETCH_ASSOC);
                        $geoTables[] = array('schema' => $schema,
                            'table' => $table['table'],
                            'column' => 'the_geom',
                            'dim' => $data['ndims'],
                            'srid' => $data['srid'],
                            'type' => $data['type']);

                        break;
                    }
                }
            }
        }

        $db->beginTransaction();
        foreach ($geoTables as $table) {
            if ($table['dim'] >= 2) {
                $sql = "DELETE FROM geometry_columns
                        WHERE f_table_catalog='' AND
                              f_table_schema='{$table['schema']}' AND
                              f_table_name='{$table['table']}' AND
                              f_geometry_column='{$table['column']}'";
                $db->exec($sql);
                $sql = "INSERT INTO geometry_columns " .
                        "VALUES ('', '{$table['schema']}', '{$table['table']}', '{$table['column']}', {$table['dim']}, {$table['srid']}, '{$table['type']}')";
                $db->exec($sql);
            }
        }
        $db->commit();
    }

    static public function getUsedMunicipalitySQL() {
        return "SELECT DISTINCT mu_id FROM common.fraction
                      UNION SELECT DISTINCT mu_id FROM common.street
                      UNION SELECT DISTINCT mu_id FROM ecogis.building
                      UNION SELECT DISTINCT mu_id FROM ecogis.action_catalog
                      UNION SELECT DISTINCT mu_id FROM ecogis.cat_munic
                      UNION SELECT DISTINCT mu_id FROM ecogis.energy_source_udm WHERE mu_id IS NOT NULL
                      UNION SELECT DISTINCT mu_id FROM ecogis.global_entry
                      UNION SELECT DISTINCT mu_id FROM ecogis.global_plain
                      UNION SELECT DISTINCT mu_id FROM ecogis.global_strategy
                      UNION SELECT DISTINCT mu_id FROM ecogis.simulation_work
                      UNION SELECT DISTINCT mu_id FROM ecogis.utility_supplier_municipality
                      UNION SELECT DISTINCT mu_id FROM ecogis.municipality WHERE mu_type='C'";
    }

    /**
     * Create a domain user (if not exists)
     */
    static public function setMunicipality($do_id, $municipality) {
        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::setMunicipality({$do_id})", ezcLog::DEBUG);
        $db = ezcDbInstance::get();
        $muListSQL = self::getUsedMunicipalitySQL();
        $db->beginTransaction();
        $sql = "UPDATE municipality SET do_id=NULL 
                WHERE do_id={$do_id} AND mu_id NOT IN ($muListSQL) AND mu_type='M'";
        $db->exec($sql);
        $data = array();
        foreach ($municipality as $id) {
            $data[] = (int) $id;
        }
        $sql = "UPDATE municipality SET do_id={$do_id} 
                WHERE mu_id IN (" . implode(', ', $data) . ") AND mu_type='M'";
        $db->exec($sql);
        $db->commit();
    }

    static public function unsetMunicipality($do_id) {
        $db = ezcDbInstance::get();
        $muListSQL = self::getUsedMunicipalitySQL();
        $sql = "UPDATE municipality SET do_id=NULL " .
                "WHERE do_id={$do_id} AND " .
                "  mu_id NOT IN ({$muListSQL})";
        $db->exec($sql);
        $sql = "SELECT COUNT(*) FROM ecogis.municipality WHERE do_id={$do_id} AND mu_type='M' AND mu_parent_id IS NULL";
        if ($db->query($sql)->fetchColumn() > 0) {
            throw new exception("Can't unset municipality");
        }
    }

    /**
     * Return the uplòoaded images
     */
    static public function getImagesList($do_name) {
        $result = array();
        $imgPath = R3_UPLOAD_DATA_DIR . strtolower($do_name) . '/logo/';
        $do_name = strtolower($do_name);
        foreach (self::$images as $inputName => $image) {
            if (file_exists("{$imgPath}{$image}.png")) {
                $result[$inputName] = true;
            }
        }
        return $result;
    }

    /**
     * Return the uplòoaded images
     */
    static public function getReferenceImages($do_name) {
        $path = R3_CONFIG_DIR . strtolower($do_name) . '/map/';
        return file_exists("{$path}reference.png");
    }

    static public function saveImages($do_name, $request) {
        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::saveImages(\"{$do_name}\")", ezcLog::DEBUG);

        // Logos
        $imgPath = R3_UPLOAD_DATA_DIR . strtolower($do_name) . '/logo/';
        $do_name = strtolower($do_name);

        foreach (self::$images as $inputName => $image) {
            if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'][0] == 0) {
                if (!move_uploaded_file($_FILES[$inputName]['tmp_name'][0], "{$imgPath}{$image}.png")) {
                    throw new Exception("Can't move temporary file from {$_FILES[$inputName]['tmp_name'][0]} to {$imgPath}{$image}.png");
                }
            }
        }
    }

    // Carica lo stile
    static public function saveCSS($do_name, $request) {
        $cssPath = R3_UPLOAD_DATA_DIR . strtolower($do_name) . '/style/';

        foreach (self::$css as $inputName => $css) {
            $fileName = "{$cssPath}{$css}.css";
            if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'][0] == 0) {
                if (!move_uploaded_file($_FILES[$inputName]['tmp_name'][0], $fileName)) {
                    throw new Exception("Can't move temporary file from {$_FILES[$inputName]['tmp_name'][0]} to {$fileName}");
                }
            } else {
                // Write an empty file (cache css)
                if (!file_exists($fileName))
                    if (!@file_put_contents($fileName, "/ * Empty css for " . $do_name . " * /\n")) {
                        throw new Exception("Can't write empty css \"{$fileName}\"");
                    }
            }
        }
    }

    // Return the extent of the domain (based on the selected municipalities)
    static public function getFullExtent($do_id, $srid) {
        $db = ezcDbInstance::get();
        $sql = "SELECT ST_Extent(the_geom) FROM municipality WHERE do_id={$do_id}";
        $result = array();
        foreach (ST_FetchBox($db->query($sql)->fetchColumn()) as $key => $val) {
            $result[$key] = (int) round($val);
        }
        return $result;
    }

    // Copia i file di template (.ini, .map)
    static public function copyTemplateFiles($do_id, $opt, $request) {
        $opt = array_merge(array('dbuser' => '',
            'dbpass' => '',
            'epsg' => ''), $opt);
        $do_name = strtolower($opt['do_name']);
        $templates = array('mapconfig.ini' => 'mapconfig.ini',
            'map.map' => 'map.map',
            'map_edit.map' => 'map_edit.map',
            'ecogis.map' => 'ecogis.map',
            'reference.png' => 'reference.png',
        );
        if ($opt['dbpass'] <> '') {
            $templates['ecogis.conn'] = '../ecogis.conn';
            @unlink(R3_CONFIG_DIR . "{$do_name}/map/{$templates['ecogis.conn']}");
        }

        $fullExtent = R3EcoGisCustomerHelper::getFullExtent($do_id, $request['cus_srid']);
        $fullExtentAsText = "{$fullExtent[0]} {$fullExtent[1]} {$fullExtent[2]} {$fullExtent[3]}";
        $schema = R3EcoGisHelper::getGeoSchema($do_id);
        foreach ($templates as $key => $val) {
            $src = R3_CUSTOMER_CONFIG_DIR . 'template/' . $key;
            $dest = R3_CONFIG_DIR . $do_name . '/map/' . $val;
            if (file_exists($src) && !file_exists($dest)) {
                $data = file_get_contents($src);
                $data = str_replace(array('<DOMAIN-NAME>',
                    '<DATABASE-HOST>',
                    '<DATABASE-NAME>',
                    '<DATABASE-USER>',
                    '<DATABASE-PASSWORD>',
                    '<DOMAIN-EPSG>',
                    '<DOMAIN-GEO>',
                    '<MAP-EXTENT>',
                    '<R3-APP-ROOT>',
                    '<R3-CONFIG-DIR>',
                    '<R3-OUTPUT-DIR>',
                    '<R3-OUTPUT-URL-PATH>',
                        ), array($do_name,
                    $opt['dbhost'],
                    $opt['dbname'],
                    $opt['dbuser'],
                    $opt['dbpass'],
                    $opt['epsg'],
                    $schema,
                    $fullExtentAsText,
                    R3_APP_ROOT,
                    R3_CONFIG_DIR,
                    R3_OUTPUT_DIR,
                    substr(R3_APP_URL, strlen(R3_DOMAIN_URL) - 1) . substr(R3_OUTPUT_DIR, strlen(R3_WEB_DIR))), $data);

                if (!@file_put_contents($dest, $data)) {
                    throw new Exception("Can't write \"{$dest}\"");
                }
            }
        }
    }

    /**
     * Copy the energy source from default to the specified domain
     * @param <type> $do_id
     */
    static public function copyEnergySource($do_id) {
        $db = ezcDbInstance::get();

        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::copyEnergySource({$do_id})", ezcLog::DEBUG);

        $sql = "SELECT * FROM energy_source_udm WHERE do_id IS NULL";
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $esu_id = $row['esu_id'];
            unset($row['esu_id']);
            unset($row['esu_uuid']);
            unset($row['do_id']);
            $existsSQL = "SELECT COUNT(*) FROM energy_source_udm WHERE es_id={$row['es_id']} AND udm_id={$row['udm_id']} AND do_id={$do_id} AND mu_id IS NULL";
            if ($db->query($existsSQL, PDO::FETCH_ASSOC)->fetchColumn() == 0) {
                $fields = implode(', ', array_keys($row));
                $insertSQL = "INSERT INTO energy_source_udm (do_id, {$fields})
                              SELECT {$do_id}, {$fields}
                              FROM energy_source_udm
                              WHERE esu_id={$esu_id}";
                $db->exec($insertSQL);
            }
        }
    }

    // Copia i parametri di configurazione dalla gestione utenti
    static public function copySettings($sourceDomain, $destinationDomain) {
        global $dbini;

        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::copySettings({$sourceDomain}, {$destinationDomain})", ezcLog::DEBUG);
        $dbiniSource = clone $dbini;
        $dbiniSource->setDomainName($sourceDomain);
        $dbiniDestination = clone $dbini;
        $dbiniDestination->setDomainName($destinationDomain);
        $destValues = $dbiniDestination->getAllValues();
        foreach ($dbiniSource->getAllValues() as $section => $data) {
            foreach ($data as $param => $value) {
                if (!isset($destValues[$section][$param])) {
                    $attribute = $dbiniSource->getAttribute($sourceDomain, APPLICATION_CODE, null, $section, $param);
                    //$dbiniDestination->setValue($section, $param, $value);
                    $dbiniDestination->setAttribute($destinationDomain, APPLICATION_CODE, null, $section, $param, $value, $attribute['se_type'], $attribute['se_type_ext'], $attribute['se_private'], $attribute['se_order'], $attribute['se_descr']);
                }
            }
        }
    }

    // Applica check e view per periodo consumi indicato (tra tutti i dominii)
    static public function applyConsumptionInterval($minYear, $maxYear) {

        $db = ezcDbInstance::get();
        $db->beginTransaction();

        $sql = "ALTER TABLE ecogis.consumption DROP CONSTRAINT IF EXISTS consumption_check_min_date RESTRICT";
        $db->exec($sql);
        $sql = "ALTER TABLE ecogis.consumption DROP CONSTRAINT IF EXISTS consumption_check_max_date RESTRICT";
        $db->exec($sql);

        if ($minYear > 0) {
            $sql = "ALTER TABLE ecogis.consumption ADD CONSTRAINT consumption_check_min_date CHECK (co_start_date >= '{$minYear}-01-01')";
            $db->exec($sql);
            $sql = "COMMENT ON CONSTRAINT consumption_check_min_date ON ecogis.consumption IS 'Minimum consumption date (Autogenerated)'";
            $db->exec($sql);
        }
        if ($maxYear > 0) {
            $sql = "ALTER TABLE ecogis.consumption ADD CONSTRAINT consumption_check_max_date CHECK (co_end_date <= '{$maxYear}-12-31')";
            $db->exec($sql);
            $sql = "COMMENT ON CONSTRAINT consumption_check_max_date ON ecogis.consumption IS 'Maximum consumption date (Autogenerated)'";
            $db->exec($sql);
        }




        $db->commit();
    }

    // Coment the database with version and update date
    static public function getDatabaseInfo() {
        $db = ezcDbInstance::get();

        $result = array('version' => null, 'update' => null);
        $dbName = $db->query("SELECT current_database()")->fetchColumn();
        $oldComment = explode("\n", $db->query("SELECT pg_catalog.shobj_description(d.oid, 'pg_database') 
                                                FROM pg_catalog.pg_database d
                                                WHERE d.datname='{$dbName}'")->fetchColumn());
        $currentDbVersion = '0.0';
        foreach ($oldComment as $line) {
            $p = strpos($line, ':');
            if ($p !== false) {
                $param = trim(substr($line, 0, $p));
                $value = trim(substr($line, $p + 1));
                if (substr($param, 0, 9) == 'database-') {
                    $result[substr($param, 9)] = $value;
                }
            }
        }
        return $result;
    }

    // Coment the database with version and update date
    static public function updateDatabaseVersion($dbVersion) {
        $db = ezcDbInstance::get();

        ezcLog::getInstance()->log("R3EcoGisCustomerHelper::updateDatabaseVersion(\"{$dbVersion}\")", ezcLog::DEBUG);

        $dbName = $db->query("SELECT current_database()")->fetchColumn();
        $oldComment = explode("\n", $db->query("SELECT pg_catalog.shobj_description(d.oid, 'pg_database') 
                                                FROM pg_catalog.pg_database d
                                                WHERE d.datname='{$dbName}'")->fetchColumn());
        $currentDbVersion = '0.0';
        foreach ($oldComment as $line) {
            $p = strpos($line, ':');
            if ($p !== false) {
                $param = trim(substr($line, 0, $p));
                $value = trim(substr($line, $p + 1));
                // echo "[$line -> '$param'='$value']";
                if ($param == 'database-version') {
                    $currentDbVersion = $value;
                }
            }
        }

        $cmp = version_compare($currentDbVersion, $dbVersion);
        if ($cmp < 0) {
            $date = date('Y-m-d H:i:s');
            $comments = "database-version: {$dbVersion}\n" .
                    "database-update: {$date}";
            $sql = "COMMENT ON DATABASE {$dbName} IS '{$comments}'";
            $db->exec($sql);
        } else if ($cmp > 0) {
            throw new exception("Invalid database version (>{$dbVersion})");
        }
    }

    static public function createGrid($do_id, $size) {
        $db = ezcDbInstance::get();
        $size = (int) $size;

        $sql = "SELECT mu_id, cus_srid 
                FROM ecogis.municipality
                INNER JOIN customer USING (do_id)
                WHERE do_id={$do_id}
                ORDER BY mu_name_1";
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $sql = "DELETE FROM ecogis.stat_grid WHERE mu_id={$row['mu_id']}";
            $db->query($sql);
            if ($size > 0) {
                $sql = "CREATE TEMPORARY TABLE tmp_grid (the_geom geometry)";
                $db->exec($sql);
                $sql = "SELECT st_extent(st_transform(the_geom, {$row['cus_srid']}))
                        FROM ecogis.municipality 
                        WHERE mu_id={$row['mu_id']}";
                $bbox = $db->query($sql)->fetchColumn();
                $bbox = str_replace(array('BOX(', ')'), '', $bbox);
                $bbox = str_replace(' ', ',', $bbox);
                $extent = explode(',', $bbox);
                $extent[0] = floor($extent[0]);
                $extent[1] = floor($extent[1]);
                $extent[2] = ceil($extent[2]);
                $extent[3] = ceil($extent[3]);
                for ($x1 = $extent[0]; $x1 <= $extent[2]; $x1+=$size) {
                    for ($y1 = $extent[1]; $y1 <= $extent[3]; $y1+=$size) {
                        $x2 = $x1 + $size;
                        $y2 = $y1 + $size;
                        $sql = "INSERT INTO tmp_grid SELECT st_setsrid(('POLYGON(({$x1} {$y1}, {$x2} {$y1}, {$x2} {$y2}, {$x1} {$y2}, {$x1} {$y1}))')::geometry, {$row['cus_srid']})";
                        $db->exec($sql);
                    }
                }
                $sql = "INSERT INTO ecogis.stat_grid (mu_id, sg_size, sg_area, the_geom) 
                        SELECT {$row['mu_id']}, {$size}, st_area(st_intersection(gr.the_geom, st_transform(mu.the_geom, {$row['cus_srid']}))), st_setsrid(st_multi(gr.the_geom), -1)
                        FROM ecogis.municipality mu, tmp_grid gr
                        WHERE mu_id={$row['mu_id']} AND mu_type='M' AND st_area(st_intersection(gr.the_geom, st_transform(mu.the_geom, {$row['cus_srid']}))) > 0";
                $db->exec($sql);
                $sql = "DROP TABLE tmp_grid";
                $db->exec($sql);
            }
        }
    }

}

class eco_customer extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'customer';

    /**
     * building fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'do_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'dn_name', 'type' => 'text', 'size' => 64, 'required' => true, 'label' => _('Dominio')),
            array('name' => 'cus_name_1', 'type' => 'text', 'size' => 80, 'required' => true, 'label' => _('Nome 1')),
            array('name' => 'cus_name_2', 'type' => 'text', 'size' => 80, 'required' => false, 'label' => _('Nome 2')),
            array('name' => 'us_name', 'type' => 'text', 'size' => 40, 'required' => $this->act == 'add', 'label' => _('Nome primo utente')),
            array('name' => 'us_login', 'type' => 'text', 'size' => 32, 'required' => $this->act == 'add', 'label' => _('Login primo utente')),
            array('name' => 'us_password', 'type' => 'text', 'size' => 32, 'required' => $this->act == 'add', 'label' => _('Password primo utente')),
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);


        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->id = initVar('id');
        $this->act = initVar('act', 'list');
        $this->order = PageVar('order', '1A', $init, false, $this->baseName);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName);
        $this->fields = $this->defFields();

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('validateDomain');
        $this->registerAjaxFunction('getSRIDDesc');
        $this->registerAjaxFunction('getMunicipality');
        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('confirmDeleteCustomer');
        $this->registerAjaxFunction('vacuum');
        $this->registerAjaxFunction('create_grid');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo ente');
            case 'mod': return _('Modifica ente');
            case 'show': return _('Visualizza ente');
            case 'list': return _('Elenco enti');
        }
        return '';  // Unknown title
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $dbInfo = R3EcoGisCustomerHelper::getDatabaseInfo();
        $this->hasDatabaseVersion = !empty($dbInfo['version']);

        $sql = "SELECT dn.do_id AS do_id_org, dn_name, cus.*, '{$dbInfo['version']}' AS db_version, '{$dbInfo['update']}' AS database_update
                FROM auth.domains d
                LEFT JOIN auth.domains_name dn ON d.do_id=dn.do_id and dn_type='N'
                LEFT JOIN auth.domains_applications da ON da.do_id=d.do_id
                LEFT JOIN auth.applications app ON da.app_id=app.app_id
                LEFT JOIN ecogis.customer cus ON cus.do_id=d.do_id
                WHERE dn.do_id>0 AND app_code=" . $db->quote(APPLICATION_CODE) . "
                AND dn_name<>'SYSTEM' ";
        if (R3_IS_MULTIDOMAIN) {
            $sql .=" AND dn_name<>" . $db->quote(DOMAIN_NAME);
        }
        return $sql;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {

        $this->simpleTable->addSimpleField(_('Dominio'), 'dn_name', 'text', 100, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Nome 1'), 'cus_name_1', 'text', null, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Nome 2'), 'cus_name_2', 'text', null, array('sortable' => true, 'order_fields' => 'fr_name, st_name, bu_nr_civic, bu_nr_civic_crossed'));
        $this->simpleTable->addSimpleField(_('SRID'), 'cus_srid', 'integer', 70, array('sortable' => true));
        if ($this->hasDatabaseVersion) {
            $this->simpleTable->addSimpleField(_('DB version'), 'database_version', 'calculated', 70, array('align' => 'center'));
            $this->simpleTable->addSimpleField(_('DB last update'), 'database_update', 'datetime', 130);
        }
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 50);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order);
    }

    public function getListTableRowOperations(&$row) {
        if ($row['db_version'] == '') {
            $this->simpleTable->addCalcValue('database_version', "<i>UNKNOWN</i>");
        } else if ($row['db_version'] <> DATABASE_VERSION) {
            $this->simpleTable->addCalcValue('database_version', "<b>{$row['db_version']}</b>");
        } else {
            $this->simpleTable->addCalcValue('database_version', $row['db_version']);
        }

        $id = $row['do_id_org'];
        $code = $row['dn_name'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "../images/ico_" . $act . ".gif");
                        break;
                    case 'del':
                        if ($this->isMultiDomain()) {
                            $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelCustomer('$code')", "", "../images/ico_" . $act . ".gif");
                        }
                        break;
                }
            }
        }
        return $links;
    }

    /**
     * Return the data for a single customer 
     */
    public function getData($id = null) {
        global /*$mdb2, */$dsn;

        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        if ($this->act == 'add') {
            $vlu = array();
            $vlu['do_template'] = DEFAULT_DOMAIN; // 'SYSTEM';
            $vlu['gr_name'] = 'ADMIN';
            $vlu['app_language'] = 1;
            $vlu['app_cat_type'] = 'IT';
            $vlu['do_building_code_type'] = 'PROPOSED';
            $vlu['do_building_code_required'] = 'F';
            $vlu['do_calculate_global_plain_totals'] = 'T';
            $vlu['do_building_code_unique'] = 'F';
            $vlu['do_building_show_id'] = 'F';
            $vlu['do_building_extra_descr'] = 'F';
            $vlu['do_public_site'] = 'F';
            $vlu['do_grid_size'] = 0;
            $vlu['do_build_year_type'] = 'TABLE';
            $vlu['do_build_restructure_year_type'] = 'TABLE';
            $vlu['do_gc_streeview'] = 'T';
            $vlu['do_gc_quick_search'] = 'T';
            $vlu['do_gc_digitize_has_selection'] = 'T';
            $vlu['do_gc_digitize_has_editing'] = 'T';
        } else {
            $sql = "SELECT dn.do_id AS do_id_org, dn_name, cus.*
                    FROM auth.domains d
                    INNER JOIN auth.domains_name dn on d.do_id=dn.do_id and dn_type='N'
                    INNER JOIN auth.domains_applications da on da.do_id=d.do_id
                    LEFT JOIN auth.applications app on da.app_id=app.app_id
                    LEFT JOIN ecogis.customer cus on cus.do_id=d.do_id
                    WHERE dn.do_id=" . $this->id;
            $vlu = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
            $data = $this->auth->getDomainData($vlu['dn_name'], true);
            $alias = array();
            foreach ($data['names'] as $name) {
                if ($vlu['dn_name'] <> $name)
                    $alias[] = $name;
            }
            $vlu['dn_name_alias'] = implode(',', $alias);
            $vlu['do_database_login'] = $dsn['dbuser'];

            $vlu['do_schema'] = R3EcoGisHelper::getGeoSchema($vlu['do_id']);
            $vlu['cus_srid_text'] = R3EcoGisCustomerHelper::getSRIDDesc($vlu['cus_srid']);

            $vlu['images'] = R3EcoGisCustomerHelper::getImagesList($vlu['dn_name']);
            $vlu['reference_image'] = R3EcoGisCustomerHelper::getReferenceImages($vlu['dn_name']);

            // Dati presi da gestione utenti
            $vlu['app_language'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'NUM_LANGUAGES');
            $vlu['app_cat_type'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'CATASTRAL_TYPE');
            $vlu['do_building_code_type'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'BUILDING_CODE_TYPE');
            $vlu['do_building_code_required'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'BUILDING_CODE_REQUIRED');
            $vlu['do_calculate_global_plain_totals'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'CALCULATE_GLOBAL_PLAIN_TOTALS');
            $vlu['do_building_code_unique'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'BUILDING_CODE_UNIQUE');
            $vlu['do_building_extra_descr'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'BUILDING_EXTRA_DESCR');
            $vlu['do_public_site'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'ENABLE_PUBLIC_SITE', 'F');
            $vlu['do_grid_size'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'STAT_GRID_SIZE', 0);
            $vlu['do_building_show_id'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'BUILDING_SHOW_ID');
            $vlu['do_build_year_type'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'BUILDING_YEAR_TYPE');
            $vlu['do_build_restructure_year_type'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'BUILDING_RESTRUCTURE_YEAR_TYPE');
            $vlu['do_municipality_mode'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'BUILDING_MUNICIPALITY_MODE');
            $vlu['do_fraction_mode'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'BUILDING_FRACTION_MODE');
            $vlu['do_street_mode'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'BUILDING_STREET_MODE');
            $vlu['do_catastral_mode'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'BUILDING_CATASTRAL_MODE');
            $vlu['do_public_css_url'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'PUBLIC_CSS_URL');

            $vlu['consumption_start_year'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'CONSUMPTION_START_YEAR');
            $vlu['consumption_end_year'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'CONSUMPTION_END_YEAR');

            $vlu['do_gc_project'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'GISCLIENT', 'PROJECT');
            $vlu['do_gc_mapset'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'GISCLIENT', 'MAPSET');
            $vlu['do_gc_streeview'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'GISCLIENT', 'HAS_STREETVIEW', 'T');
            $vlu['do_gc_quick_search'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'GISCLIENT', 'HAS_QUICK_SEARCH', 'T');
            $vlu['do_gc_digitize_has_selection'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'GISCLIENT', 'DIGITIZE_HAS_SELECTION', 'T');
            $vlu['do_gc_digitize_has_editing'] = $this->auth->getConfigValueFor($vlu['dn_name'], APPLICATION_CODE, null, 'GISCLIENT', 'DIGITIZE_HAS_EDITING', 'T');
            if ($vlu['do_grid_size'] == 0) {
                $vlu['do_grid_size'] = '';
            }
        }

        $catalog = R3DbCatalog::factory($db);
        $data = $catalog->getUserData();
        $vlu['can_create_db_user'] = $data['rolsuper'] == 't' || $data['rolcreaterole'] == 't';
        $vlu['do_database_version'] = DATABASE_VERSION;

        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Return the data for a single customer 
     */
    public function getLookupData($id = null) {
        $lkp = array();

        $trueFalse = array('T' => _('Si'), 'F' => _('No'));
        $lkp['do_template_values'] = R3EcoGisCustomerHelper::getDomainsList($this->auth);
        $lkp['do_group_values'] = R3EcoGisCustomerHelper::getGroupsList($this->auth);
        $lkp['app_language_values'] = array(1 => _('Monolingue'), 2 => _('Bilingue'));
        $lkp['app_cat_type_values'] = array('ITALY' => _('Italiano'), 'AUSTRIA' => _('Austriaco'));
        $lkp['do_build_year_type_values'] = array('FREE' => _('Inserimento libero'), 'TABLE' => _('Selezione da tabella'));
        $lkp['do_build_restructure_year_type_values'] = array('FREE' => _('Inserimento libero'), 'TABLE' => _('Selezione da tabella'));
        $lkp['do_municipality_mode_values'] = array('COMBO' => _('Combo box'), 'AUTOCOMPLETE' => _('Autocomplete'));
        $lkp['do_building_code_type_values'] = array('NONE' => _('Nessuno'), 'AUTO' => _('Automatico'), 'MANUAL' => _('Manuale'), 'PROPOSED' => _('Proposto'));
        $lkp['true_false_values'] = $trueFalse;
        $lkp['do_fraction_mode_values'] = array('COMBO' => _('Combo box'), 'AUTOCOMPLETE' => _('Autocomplete'));
        $lkp['do_street_mode_values'] = $lkp['do_fraction_mode_values'];
        $lkp['do_catastral_mode_values'] = $lkp['do_fraction_mode_values'];
        $lkp['pr_list'] = R3EcoGisHelper::getProvinceList(null);
        $lkp['mu_list'] = R3EcoGisCustomerHelper::getAvailableMunicipalityList();
        if ($this->act == 'mod') {
            $lkp['mu_selected'] = R3EcoGisCustomerHelper::getSelectedMunicipalityList($this->data['do_id']);
        }
        return $lkp;
    }

    public function isMultiDomain() {
        return R3_IS_MULTIDOMAIN;
    }

    public function getPageVars() {
        $db = ezcDbInstance::get();

        $warningText = '';
        if (!$this->isMultiDomain() && $db->query("SELECT COUNT(*) FROM ecogis.customer")->fetchColumn() > 1) {
            $warningText = _("Warning: You have configured more domains on a NON-MULTIDOMAIN configuration. Check the R3_IS_MULTIDOMAIN parameter");
        }
        return array('warningText' => $warningText, 'isMultiDomain' => $this->isMultiDomain());
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        return array('txtChangeSRIDWarning' => _('ATTENZIONE! Il cambio dello SRID ha effetto solo sui dati nuovi e nelle view standard'));
    }

    /**
     * Validate the domain and return kind data
     * @param array $request    the request
     * @return text             the help text (usually html)
     */
    public function validateDomain($request) {
        //global $dsn;

        $result = array();
        $validChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890_';
        $validPwChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $request['dn_name'] = strToUpper($request['dn_name']);
        $request['dn_name'] = str_replace(' ', '_', $request['dn_name']);
        $domain = '';
        for ($i = 0; $i < strlen($request['dn_name']); $i++) {
            if (strpos($validChars, $request['dn_name'][$i]) !== false) {
                $domain .= $request['dn_name'][$i];
            }
        }
        $result['dn_name'] = $domain;

        $request['dn_name_alias'] = strToUpper($request['dn_name_alias']);
        $request['dn_name_alias'] = str_replace(' ', '_', $request['dn_name_alias']);
        $domain_alias = '';
        $validChars = $validChars . ',;';
        for ($i = 0; $i < strlen($request['dn_name_alias']); $i++) {
            if (strpos($validChars, $request['dn_name_alias'][$i]) !== false) {
                $domain_alias .= $request['dn_name_alias'][$i];
            }
        }
        $result['dn_name_alias'] = $domain_alias;

        if (isset($request['is_alias']) && $request['is_alias'] == 'F') {
            if (in_array($domain, R3EcoGisCustomerHelper::getDomainsList($this->auth)))
                return array('status' => R3_AJAX_ERROR,
                    'error' => array('text' => 'Attenzione! Dominio già esistente'));
            // Generate password
            $domainLower = strtolower($domain);
            $pw = '';
            $pw2 = '';
            for ($i = 0; $i < 8; $i++) {
                $pw .= $validPwChars[rand(0, strlen($validPwChars) - 1)];
                $pw2 .= $validPwChars[rand(0, strlen($validPwChars) - 1)];
            }
            $result['cus_name_1'] = UCWords(str_replace('_', ' ', $domainLower));
            $result['cus_name_2'] = $result['cus_name_1'];
            $result['us_name'] = 'Administrator (' . UCFirst($domainLower) . ')';
            $result['us_login'] = 'admin';
            $result['us_password'] = $pw2;
            $result['dn_name_lower'] = $domainLower;
            if ($this->isMultiDomain()) {
                $result['do_schema'] = 'geo_' . $domainLower;
                $result['do_gc_mapset'] = 'r3-ecogis-' . strtolower($request['dn_name']);
            } else {
                $result['do_schema'] = 'geo';
                $result['do_gc_mapset'] = 'r3-ecogis';
            }
            $result['do_gc_project'] = strtolower($request['dn_name']);
        }
        return $result;
    }

    /**
     * Return the help data (ajax)
     * @param array $request    the request
     * @return text             the help text (usually html)
     */
    public function getSRIDDesc($request) {
        return array('text' => R3EcoGisCustomerHelper::getSRIDDesc($request['cus_srid']));
    }

    public function getMunicipality($request) {
        $opt = array('pr_id' => $request['pr_id'],
            'mu_name' => $request['mu_name']);
        return array('data' => R3EcoGisCustomerHelper::getAvailableMunicipalityList($opt));
    }

    /**
     * Delete a customer
     * @param array  $request
     */
    public function deleteCustomer($request) {
        // Drop the standard views
        $data = $this->auth->getDomainData($request['id']);
        $schema = R3EcoGisHelper::getGeoSchema($data['do_id']); //   'geo_' . strtolower($data['dn_name']);
        //$user = 'ecogis_' . strtolower($data['dn_name']);
        R3EcoGisCustomerHelper::dropViews($schema);
        R3EcoGisCustomerHelper::dropSchema($schema);

        $db = ezcDbInstance::get();
        $db->beginTransaction();
        R3EcoGisCustomerHelper::unsetMunicipality($data['do_id']);
        R3EcoGisCustomerHelper::deleteDomainLogs($data['do_id']);
        R3EcoGisCustomerHelper::deleteDomainSettings($data['do_id']);
        R3EcoGisCustomerHelper::deleteDomainUsers($data['do_id']);
        R3EcoGisCustomerHelper::deleteDomainEnergySource($data['do_id']);
        R3EcoGisCustomerHelper::deleteCustomer($data['do_id']);
        $db->commit();
        R3EcoGisCustomerHelper::dropDomain($data['dn_name']);
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        global $dbini, $dsn, /*$mdb2, */$languages;
        if ($this->act == 'del') {
            $id = $this->deleteCustomer($request);
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneCustomer($id)");
        }
        $errors = array();
        $errors = $this->checkFormData($request, $errors);
        if ($request['municipality'] == '') {
            $errors['municipality'] = array('CUSTOM_ERROR' => _("Almeno un comune deve essere selezionato"));
        }
        $db = ezcDbInstance::get();
        $catalog = R3DbCatalog::factory($db);
        $data = $catalog->getUserData();

        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            if ($request['cus_srid'] == '') {
                $request['cus_srid'] = 4326;
            }

            $db = ezcDbInstance::get();
            $do_id = R3EcoGisCustomerHelper::createDomain($request['dn_name'], $request['dn_name_alias']);
            R3EcoGisCustomerHelper::createCustomer($do_id, $request);
            R3EcoGisCustomerHelper::createUser($do_id, $request);
            R3EcoGisCustomerHelper::setMunicipality($do_id, explode(',', $request['municipality']));

            $dataPath = R3_UPLOAD_DATA_DIR . strtolower($request['dn_name']);
            $dataLogoPath = $dataPath . '/logo';
            $dataStylePath = $dataPath . '/style';
            $dataJSPath = $dataPath . '/js';

            if (!file_exists($dataPath)) {
                if (!@mkdir($dataPath)) {
                    throw new Exception("Can't create path {$dataPath}");
                }
            }
            if (!file_exists($dataLogoPath)) {
                if (!@mkdir($dataLogoPath)) {
                    throw new Exception("Can't create path {$dataLogoPath}");
                }
            }
            if (!file_exists($dataStylePath)) {
                if (!@mkdir($dataStylePath)) {
                    throw new Exception("Can't create path {$dataStylePath}");
                }
            }
            if (!file_exists($dataJSPath)) {
                if (!@mkdir($dataJSPath)) {
                    throw new Exception("Can't create path {$dataJSPath}");
                }
            }

            R3EcoGisCustomerHelper::saveImages($request['dn_name'], $request);
            R3EcoGisCustomerHelper::saveCSS($request['dn_name'], $request);

            if ($request['consumption_end_year'] > 0 && $request['consumption_start_year'] > 0) {
                $viewOpt = array('totYears' => $request['consumption_end_year'] - $request['consumption_start_year'] + 1);
            } else {
                $viewOpt = array('totYears' => 100);
            }
            $schema = R3EcoGisHelper::getGeoSchema($do_id); //'geo_' . strtolower($request['dn_name']);
            R3EcoGisCustomerHelper::createSchema($schema, $dsn['dbuser']/* $request['do_database_login'] */);
            R3EcoGisCustomerHelper::createViews($schema, $dsn['dbuser'] /* $request['do_database_login'] */, $request['cus_srid'], $do_id, $viewOpt);
            R3EcoGisCustomerHelper::setLoggerTrigger();
            R3EcoGisCustomerHelper::grantTables($schema, $dsn['dbuser']/* $request['do_database_login'] */);
            R3EcoGisCustomerHelper::applyConsumptionInterval($request['consumption_start_year'], $request['consumption_end_year']);
            if (R3_AUTO_POPULATE_GEOMETRY_COLUMNS && !isset($request['skip_geometry_check'])) {
                R3EcoGisCustomerHelper::populateGeometryColumns(R3_DB_SCHEMA);
                R3EcoGisCustomerHelper::populateGeometryColumns($schema);
            }

            R3EcoGisCustomerHelper::copyEnergySource($do_id);
            if ($this->isMultiDomain()) {
                R3EcoGisCustomerHelper::copySettings('SYSTEM', $request['dn_name']);
            }
            if ($this->act == 'add') {
                $sql = "INSERT INTO auth.settings (do_id, app_id, se_section, se_param, se_value, se_descr, se_type, se_type_ext, se_private, se_order)
                        SELECT DISTINCT (SELECT do_id FROM auth.domains_name WHERE dn_type='N' and dn_name='{$request['dn_name']}'), s.app_id, se_section, se_param, se_value, se_descr, se_type, se_type_ext, se_private, se_order
                        FROM auth.settings s
                        INNER JOIN auth.domains_name dn on s.do_id=dn.do_id and dn_type='N' AND dn_name='{$request['do_template']}'
                        INNER JOIN auth.applications app on s.app_id=app.app_id AND app_code='" . APPLICATION_CODE . "'
                        WHERE us_id IS NULL 
                        ORDER BY se_section, se_param";
                $db->exec($sql);
            }

            //Salva dati nuovo ente (DBINI)
            $gridSize = forceInteger($request['do_grid_size'], 0, false, '.');
            if ($this->auth->getConfigValueFor($request['dn_name'], APPLICATION_CODE, null, 'APPLICATION', 'STAT_GRID_SIZE', 0) <> $gridSize) {
                $this->create_grid(array('do_id' => $do_id, 'sg_size' => $request['do_grid_size']));
            }
            $dbini2 = clone $dbini;
            $dbini2->setDomainName($request['dn_name']);
            $dbini2->setValue('APPLICATION', 'NUM_LANGUAGES', $request['app_language']);
            $dbini2->setValue('APPLICATION', 'CATASTRAL_TYPE', $request['app_cat_type']);
            $dbini2->setValue('APPLICATION', 'BUILDING_CODE_TYPE', $request['do_building_code_type']);
            $dbini2->setValue('APPLICATION', 'BUILDING_CODE_REQUIRED', $request['do_building_code_required']);
            $dbini2->setValue('APPLICATION', 'CALCULATE_GLOBAL_PLAIN_TOTALS', $request['do_calculate_global_plain_totals']);
            $dbini2->setValue('APPLICATION', 'BUILDING_CODE_UNIQUE', $request['do_building_code_unique']);
            $dbini2->setValue('APPLICATION', 'BUILDING_EXTRA_DESCR', $request['do_building_extra_descr']);
            $dbini2->setValue('APPLICATION', 'PUBLIC_CSS_URL', $request['do_public_css_url']);
            $dbini2->setValue('APPLICATION', 'ENABLE_PUBLIC_SITE', $request['do_public_site']);
            $dbini2->setValue('APPLICATION', 'STAT_GRID_SIZE', $gridSize > 0 ? $gridSize : null);
            $dbini2->setValue('APPLICATION', 'BUILDING_SHOW_ID', $request['do_building_show_id']);
            $dbini2->setValue('APPLICATION', 'BUILDING_YEAR_TYPE', $request['do_build_year_type']);
            $dbini2->setValue('APPLICATION', 'BUILDING_RESTRUCTURE_YEAR_TYPE', $request['do_build_restructure_year_type']);
            $dbini2->setValue('APPLICATION', 'BUILDING_MUNICIPALITY_MODE', $request['do_municipality_mode']);
            $dbini2->setValue('APPLICATION', 'BUILDING_FRACTION_MODE', $request['do_fraction_mode']);
            $dbini2->setValue('APPLICATION', 'BUILDING_STREET_MODE', $request['do_street_mode']);
            $dbini2->setValue('APPLICATION', 'BUILDING_CATASTRAL_MODE', $request['do_catastral_mode']);
            $dbini2->setValue('APPLICATION', 'CONSUMPTION_START_YEAR', $request['consumption_start_year']);
            $dbini2->setValue('APPLICATION', 'CONSUMPTION_END_YEAR', $request['consumption_end_year']);

            if ($request['app_language'] == 1) {
                $dbini2->setValue('APPLICATION', 'LANG_NAME_SHORT_1', '');
                $dbini2->setValue('APPLICATION', 'LANG_NAME_SHORT_2', '');
            } else {
                $dbini2->setValue('APPLICATION', 'LANG_NAME_SHORT_1', sprintf(' (%s)', $languages[1]));
                $dbini2->setValue('APPLICATION', 'LANG_NAME_SHORT_2', sprintf(' (%s)', $languages[2]));
            }
            $dbini2->setValue('APPLICATION', 'LANG_NAME_SHORT_3', ' (en)');

            // Gisclient settings
            $dbini2->setValue('GISCLIENT', 'PROJECT', $request['do_gc_project']);
            $dbini2->setValue('GISCLIENT', 'MAPSET', $request['do_gc_mapset']);
            $dbini2->setValue('GISCLIENT', 'HAS_STREETVIEW', $request['do_gc_streeview']);
            $dbini2->setValue('GISCLIENT', 'HAS_QUICK_SEARCH', $request['do_gc_quick_search']);

            $dbini2->setValue('GISCLIENT', 'DIGITIZE_HAS_SELECTION', $request['do_gc_digitize_has_selection']);
            $dbini2->setValue('GISCLIENT', 'DIGITIZE_HAS_EDITING', $request['do_gc_digitize_has_editing']);

            if (R3_AUTO_SAVE_DATABASE_VERSION) {
                R3EcoGisCustomerHelper::updateDatabaseVersion(DATABASE_VERSION);  // Setup database version
            }
            $id = 0;
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneCustomer($id)");
        }
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function confirmDeleteCustomer($request) {
        //global $mdb2;

        $db = ezcDbInstance::get();
        $catalog = R3DbCatalog::factory($db);
        $data = $this->auth->getDomainData($request['id']);
        if ($this->isMultiDomain()) {
            $schema = 'geo_' . $data['dn_name'];
        } else {
            $schema = 'geo';
        }
        $check = array(
            'fraction_data' => 'frazioni',
            'street_data' => 'strade',
            'cat_munic_data' => 'comuni catastali',
            'building_data' => 'edifici',
            'action_catalog_data' => 'catalogo azioni',
            'simulation_work_data' => 'simulazioni',
            'global_plain_data' => 'parametri generali PAES',
            'global_entry_data' => 'inventario emissioni',
            'global_strategy_data' => 'PAES',
            'utility_supplier' => 'Fornitori di energia',
        );

        $do_id = (int) $data['do_id'];
        foreach ($check as $table => $text) {
            if ($db->query("SELECT COUNT(*) FROM {$table} WHERE do_id={$do_id}")->fetchColumn() > 0)
                return array('status' => R3_AJAX_NO_ERROR, 'alert' => _("Impossibile cancellare l'ente {$data['dn_name']} perchè esistono {$text}"));
        }
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => sprintf(_("Sei sicuro di voler cancellare definitivamente l'ente \"{$data['dn_name']}\" e tutti i dati ad esso associato?"), 'name'));
    }

    private function tableHasClusterIndex($schemaName, $tableName) {
        $db = ezcDbInstance::get();
        $sql = "SELECT COUNT(*)
                FROM pg_catalog.pg_class c
                INNER JOIN pg_catalog.pg_roles r ON r.oid = c.relowner
                LEFT JOIN pg_catalog.pg_namespace n ON n.oid = c.relnamespace 
                LEFT JOIN pg_catalog.pg_index i ON i.indexrelid = c.oid 
                LEFT JOIN pg_catalog.pg_class c2 ON i.indrelid = c2.oid 
                WHERE nspname='{$schemaName}' AND c2.relname = '{$tableName}' AND indisclustered IS TRUE";
        return $db->query($sql)->fetchColumn();
    }

    public function vacuum($request) {
        session_write_close();

        $db = ezcDbInstance::get();
        $sql = "SELECT table_schema, table_name
                FROM information_schema.tables 
                WHERE table_type='BASE TABLE' AND table_schema !~ '^pg_' AND table_schema<>'information_schema' 
                ORDER BY table_schema, table_name";
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            if ($this->tableHasClusterindex($row['table_schema'], $row['table_name'])) {
                $sql = "CLUSTER {$row['table_schema']}.{$row['table_name']}";
                $db->exec($sql);
            }
            $sql = "REINDEX TABLE {$row['table_schema']}.{$row['table_name']}";
            $db->exec($sql);

            $sql = "VACUUM FULL ANALYZE {$row['table_schema']}.{$row['table_name']}";
            $db->exec($sql);
        }
        return array('status' => R3_AJAX_NO_ERROR);
    }

    public function create_grid($request) {
        global $dbini;

        $db = ezcDbInstance::get();

        session_write_close();
        ignore_user_abort();
        //transaction

        $gridSize = (int) $request['sg_size'];
        $db->beginTransaction();
        R3EcoGisCustomerHelper::createGrid((int) $request['do_id'], $gridSize);

        $dnName = R3EcoGisHelper::getDomainCodeFromID($request['do_id']);
        $dbini2 = clone $dbini;
        $dbini2->setDomainName($dnName);
        $dbini2->setValue('APPLICATION', 'STAT_GRID_SIZE', $gridSize > 0 ? $gridSize : null);
        $db->commit();
        return array('status' => R3_AJAX_NO_ERROR);
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        // Extra security checkdate
        if (!$this->auth->hasPerm($act, 'ALL_DOMAINS')) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
