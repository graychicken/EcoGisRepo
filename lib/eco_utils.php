<?php

class R3httpException extends Exception {

    protected $extraMessage;

    public function __construct($message, $code = 0, $extraMessage = null, Exception $previous = null) {

        $this->extraMessage = $extraMessage;
        parent::__construct($message, $code, $previous);
    }

    public function getExtraMessage() {
        return $this->extraMessage;
    }

}

class R3Opt {

    static function getOptList($table, $keyName, $valueName, array $options = array()) {
        $defaultOptions = array('order' => $valueName,
            'inner_join' => null,
            'constraints' => null,
            'allow_empty' => false,
            'skip_empty' => false,
            'group_by' => false,
            'limit' => null,
            'show_query' => false);
        $options = array_merge($defaultOptions, $options);
        $db = ezcDbInstance::get();
        $q = $db->createSelectQuery();
        $q->select("{$table}.$keyName", "{$table}.$valueName")
                ->from($table);
        if (isset($options['inner_join'])) {
            if (count($options['inner_join']) == 2) {
                // only 1 field name given
                $options['inner_join'][2] = $options['inner_join'][1];
            }
            $q->innerJoin($options['inner_join'][0], $q->expr->eq("{$table}.{$options['inner_join'][1]}", "{$options['inner_join'][0]}.{$options['inner_join'][2]}"));
        }

        // handle eventual filters
        if (isset($options['constraints'])) {

            if (is_string($options['constraints'])) {
                $constraints = array($options['constraints']);
            } else {
                $constraints = $options['constraints'];
            }
            $q->where($constraints);
        }
        if ($options['group_by'] === true) {
            $q->groupBy("{$table}.$keyName", "{$table}.$valueName");
        }
        $q->orderBy($options['order']);
        if ($options['limit'] !== null) {
            $q->limit($options['limit']);
        }
        if ($options['show_query']) {
            echo " $q <br>\n";
        }
        return self::execOptionsQuery($q, $keyName, $valueName, $options);
    }

    static function execOptionsQuery(ezcQuerySelect $query, $keyName, $valueName, array $options = array()) {
        $defaultOptions = array(
            'skip_empty' => false,
            'allow_empty' => false,
            'empty_text' => _('-- Selezionare --'));
        $opt = array_merge($defaultOptions, $options);
        $stmt = $query->prepare();

        $stmt->execute();
        $optionsList = array();
        if ($opt['allow_empty']) {
            $optionsList[''] = $opt['empty_text'];
        }
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($opt['skip_empty'] !== true || trim($row[$valueName]) <> '') {
                $optionsList[$row[$keyName]] = $row[$valueName];
            }
        }
        return $optionsList;
    }

    static function addChooseItem(array $data, array $opt = array()) {
        $defaultOptions = array(
            'allow_empty' => true,
            'empty_text' => _('-- Selezionare --'));
        $opt = array_merge($defaultOptions, $opt);
        $optionsList = array();
        if (!$opt['allow_empty']) {
            return $data;
        }
        $optionsList[''] = $opt['empty_text'];
        foreach ($data as $key => $val) {
            $optionsList[$key] = $val;
        }
        return $optionsList;
    }

}

/**
 * Utility function class for EcoGIS
 */
class R3EcoGisHelper {

    static function includeHelperClass($file) {
        require_once R3_CLASS_DIR . $file;
    }

    /**
     * Force a JSon array to prevent json object sort
     * @param mixed $mixed     input data
     * @param array            array with json
     */
    static function forceJSONArray(array $mixed) {
        $result = array();
        foreach ($mixed as $key => $val) {
            $result[][$key] = $val;
        }
        return $result;
    }

    static public function addImport($do_id, $name, $fileName, $descr_1 = null, $descr_2 = null) {
        $db = ezcDbInstance::get();

        $data = file_get_contents($fileName);

        $sql = "INSERT INTO ecogis.import (im_name, im_descr_1, im_descr_2, im_file, do_id) VALUES (:im_name, :im_descr_1, :im_descr_2, :im_file, :do_id)";
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':im_name', $name, PDO::PARAM_STR);
        $stmt->bindParam(':im_descr_1', $descr_1, PDO::PARAM_STR);
        $stmt->bindParam(':im_descr_2', $descr_2, PDO::PARAM_STR);
        $stmt->bindParam(':im_file', $data, PDO::PARAM_LOB);
        $stmt->bindParam(':do_id', $do_id, PDO::PARAM_INT);
        $stmt->execute();
        return $db->lastInsertId('import_im_id_seq');
    }

    /**
     * Return the current domain name
     * @param integer|null $do_id        the domain id. If null the current domain is used
     * return array                     the domain list
     */
    static public function getCurrentDomainName() {
        $auth = R3AuthInstance::get();
        return $auth->getDomainName();
    }

    /**
     * Return the domain list
     *
     * return array                     the domain list
     */
    static public function getDomainList($do_id = null) {
        static $cache = null;
        if (!isset($cache)) {
            $cache = R3Opt::getOptList('customer_data', 'do_id', 'cus_name_' . R3Locale::getLanguageID());
        }
        if ($do_id === null) {
            return $cache;
        }
        return isset($cache[$do_id]) ? $cache[$do_id] : null;
    }

    /**
     * Return the domain name
     * @param integer|null $do_id        the domain id. If null the current domain is used
     * return array                     the domain list
     */
    static public function getDomainName($do_id = null) {
        return R3EcoGisHelper::getDomainList($do_id);
    }

    /**
     * Return the domain list
     *
     * return array                     the domain list
     */
    static public function getDomainCodeFromID($do_id) {
        static $cache = null;
        if (!isset($cache)) {
            $db = ezcDbInstance::get();
            $do_id = (int) $do_id;
            $sql = "SELECT dn_name FROM auth.domains_name WHERE do_id={$do_id} AND dn_type='N'";
            $cache[$do_id] = $db->query($sql)->fetchColumn();
        }
        return $cache[$do_id];
    }

    /**
     * Return the province list for the given domain (Customer)
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the province list
     */
    static public function getProvinceList($do_id, array $opt = array()) {
        static $cache = null;
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $constraints = $do_id === null ? array() : array('constraints' => 'province_data.do_id=' . $db->quote($do_id));

        if (isset($opt['join_with_building']) && $opt['join_with_building'] == true) {
            $constraints['inner_join'] = array('building_data', 'pr_id');
            return R3Opt::getOptList('province_data', 'pr_id', 'pr_name_' . $lang, $constraints);
        } else if (isset($opt['join_with_street_lighting']) && $opt['join_with_street_lighting'] == true) {
            $constraints['inner_join'] = array('street_lighting_data', 'pr_id');
            return R3Opt::getOptList('province_data', 'pr_id', 'pr_name_' . $lang, $constraints);
        } else if (isset($opt['join_with_global_strategy']) && $opt['join_with_global_strategy'] == true) {
            $constraints['inner_join'] = array('global_strategy_data', 'pr_id');
            return R3Opt::getOptList('province_data', 'pr_id', 'pr_name_' . $lang, $constraints);
        } else if (isset($opt['join_with_global_result']) && $opt['join_with_global_result'] == true) {
            $constraints['inner_join'] = array('global_entry_data', 'pr_id');
            return R3Opt::getOptList('province_data', 'pr_id', 'pr_name_' . $lang, $constraints);
        } else if (isset($opt['join_with_action_catalog']) && $opt['join_with_action_catalog'] == true) {
            $constraints['inner_join'] = array('action_catalog_data', 'pr_id');
            return R3Opt::getOptList('province_data', 'pr_id', 'pr_name_' . $lang, $constraints);
        } else if (isset($opt['join_with_simulation']) && $opt['join_with_simulation'] == true) {
            $constraints['inner_join'] = array('simulation_work_data', 'pr_id');
            return R3Opt::getOptList('province_data', 'pr_id', 'pr_name_' . $lang, $constraints);
        } else if (isset($opt['join_with_global_plain']) && $opt['join_with_global_plain'] == true) {
            $constraints['inner_join'] = array('global_plain_data', 'pr_id');
            return R3Opt::getOptList('province_data', 'pr_id', 'pr_name_' . $lang, $constraints);
        } else {
            // Standard request: Use cache
            if (!isset($cache)) {
                $constraints['group_by'] = true;
                $cache = R3Opt::getOptList('province_data', 'pr_id', 'pr_name_' . R3Locale::getLanguageID(), $constraints);
            }
        }
        return $cache;
    }

    /**
     * Return the municipality list for the given domain (Customer)
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the municipality list
     */
    static public function getMunicipalityList($do_id, $like = null, $limit = null, array $opt = array()) {
        static $cache = null;
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        if ($like == '') {
            $constraints = $do_id === null ? array() : array('constraints' => 'municipality_data.do_id=' . (int) $do_id);
            $constraints['group_by'] = true;
            if (isset($opt['join_with_building']) && $opt['join_with_building'] == true) {
                $constraints['inner_join'] = array('building', 'mu_id');
                return R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_street_lighting']) && $opt['join_with_street_lighting'] == true) {
                $constraints['inner_join'] = array('street_lighting_data', 'mu_id');
                return R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_global_strategy']) && $opt['join_with_global_strategy'] == true) {
                $constraints['inner_join'] = array('global_strategy_data', 'mu_id');
                return R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_global_result']) && $opt['join_with_global_result'] == true) {
                $constraints['inner_join'] = array('global_entry_data', 'mu_id');
                return R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_action_catalog']) && $opt['join_with_action_catalog'] == true) {
                $constraints['inner_join'] = array('action_catalog_data', 'mu_id');
                return R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_simulation']) && $opt['join_with_simulation'] == true) {
                $constraints['inner_join'] = array('simulation_work_data', 'mu_id');
                return R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_global_plain']) && $opt['join_with_global_plain'] == true) {
                $constraints['inner_join'] = array('global_plain_data', 'mu_id');
                return R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_global_strategy_paes']) && $opt['join_with_global_strategy_paes'] == true) {
                $constraints['inner_join'] = array('global_plain_data', 'mu_id');
                $constraints['constraints'] = 'gp_id IS NOT NULL';
                return R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else {
                // Standard request: Use cache
                if (!isset($cache[$do_id])) {
                    $cache[$do_id] = R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_' . $lang, $constraints);
                }
                return $cache[$do_id];
            }
        } else {
            // Can't cache
            $like = trim($like);
            $limit = (int) $limit;
            $sql = "SELECT mu_id, mu_name_$lang AS mu_name " .
                    "FROM municipality_data " .
                    "WHERE do_id=" . (int) $do_id . " AND " .
                    "  mu_name_$lang ILIKE " . $db->quote("%{$like}%") . " " .
                    "ORDER BY mu_name_$lang ";
            if ($limit > 0) {
                $sql .= "LIMIT $limit";
            }
            $result = array();
            foreach ($db->query($sql) as $row) {
                $result[$row['mu_id']] = $row['mu_name'];
            }
            return $result;
        }
    }

    static public function getMunicipalityCollectionList($do_id, $like = null, $limit = null, array $opt = array()) {
        static $cache = null;

        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        if ($like == '') {
            $constraints = $do_id === null ? array() : array('constraints' => 'municipality_collection_data.do_id=' . (int) $do_id);
            $constraints['group_by'] = true;
            if (isset($opt['join_with_building']) && $opt['join_with_building'] == true) {
                $constraints['inner_join'] = array('building', 'mu_id');
                return R3Opt::getOptList('municipality_collection_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_street_lighting']) && $opt['join_with_street_lighting'] == true) {
                $constraints['inner_join'] = array('street_lighting_data', 'mu_id');
                return R3Opt::getOptList('municipality_collection_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_global_strategy']) && $opt['join_with_global_strategy'] == true) {
                $constraints['inner_join'] = array('global_strategy_data', 'mu_id');
                return R3Opt::getOptList('municipality_collection_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_global_result']) && $opt['join_with_global_result'] == true) {
                $constraints['inner_join'] = array('global_entry_data', 'mu_id');
                return R3Opt::getOptList('municipality_collection_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_action_catalog']) && $opt['join_with_action_catalog'] == true) {
                $constraints['inner_join'] = array('action_catalog_data', 'mu_id');
                return R3Opt::getOptList('municipality_collection_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_simulation']) && $opt['join_with_simulation'] == true) {
                $constraints['inner_join'] = array('simulation_work_data', 'mu_id');
                return R3Opt::getOptList('municipality_collection_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_global_plain']) && $opt['join_with_global_plain'] == true) {
                $constraints['inner_join'] = array('global_plain_data', 'mu_id');
                return R3Opt::getOptList('municipality_collection_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else if (isset($opt['join_with_global_strategy_paes']) && $opt['join_with_global_strategy_paes'] == true) {
                $constraints['inner_join'] = array('global_plain_data', 'mu_id');
                $constraints['constraints'] = 'gp_id IS NOT NULL';
                return R3Opt::getOptList('municipality_collection_data', 'mu_id', 'mu_name_' . $lang, $constraints);
            } else {
                // Standard request: Use cache
                if (!isset($cache[$do_id])) {
                    $cache[$do_id] = R3Opt::getOptList('municipality_collection_data', 'mu_id', 'mu_name_' . $lang, $constraints);
                }
            }
            return $cache[$do_id];
        } else {
            throw new Exception('Not implemented in getMunicipalityCollectionList');
        }
        return $cache[$do_id];
    }

    /**
     * If has municipality collection, return a 2D array with municipality and municipality collection.
     * if anly municipality present return only the municipality list in 1Dim array. Used for option and optiongroup in html select
     * 
     * @param type $do_id
     * @return type
     */
    static public function getMunicipalityAndMunicipalityCollectionList($do_id, $like = null, $limit = null, array $opt = array()) {
        $result = array();
        $result['has_municipality_collection'] = R3EcoGisHelper::hasMunicipalityCollection($do_id);
        if ($result['has_municipality_collection']) {
            $munucipalityCollectionList = R3EcoGisHelper::getMunicipalityCollectionList($do_id, $like, $limit, $opt);
            $munucipalityList = R3EcoGisHelper::getMunicipalityList($do_id, $like, $limit, $opt);
            if (count($munucipalityCollectionList) > 0) {
                $result['data'][_('Raggruppamenti')] = $munucipalityCollectionList;
                $result['tot']['collection'] = count($result['data'][_('Raggruppamenti')]);
            } else {
                $result['tot']['collection'] = 0;
            }
            if (count($munucipalityList) > 0) {
                $result['data'][_('Comuni')] = $munucipalityList;
                $result['tot']['municipality'] = count($result['data'][_('Comuni')]);
            } else {
                $result['tot']['municipality'] = 0;
            }
        } else {
            $result['data'] = R3EcoGisHelper::getMunicipalityList($do_id, $like, $limit, $opt);
            $result['tot']['municipality'] = count($result['data']);
        }
        return $result;
    }

    /**
     * Return tru if municipality collection is available for the specified domain
     * @param type $do_id   domain id
     * @return boolean
     */
    static public function hasMunicipalityCollection($do_id) {
        static $result = array();

        if (!array_key_exists($do_id, $result)) {
            $sql = "SELECT COUNT(*)
                FROM ecogis.municipality
                WHERE do_id=:do_id AND mu_type='C'";
            $stmt = ezcDbInstance::get()->prepare($sql);
            $stmt->execute(array('do_id' => $do_id));
            $result[$do_id] = $stmt->fetchColumn() > 0;
        }
        return $result[$do_id];
    }

    /**
     * Return "Municipality" or "Municipality/munic. collection"
     * @param type $do_id
     * @return string (translated)
     */
    static public function geti18nMunicipalityLabel($do_id) {
        return self::hasMunicipalityCollection($do_id) ? _('Comune/raggruppamento') : _('Comune');
    }

    /**
     * Return the number of municipality associated with the domain
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the municipality list
     */
    static public function getMunicipalityCount($do_id) {
        $db = ezcDbInstance::get();
        $sql = "SELECT COUNT(*) 
                FROM municipality
                WHERE do_id=" . (int) $do_id;
        return $db->query($sql)->fetchColumn();
    }

    /**
     * Return the municipality list for the given domain (Customer)
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the municipality list
     */
    static public function getMunicipalityName($mu_id, $lang = null) {
        static $cache = null;
        if ($lang === null) {
            $lang = R3Locale::getLanguageID();
        }
        $db = ezcDbInstance::get();
        if (!isset($cache[$mu_id])) {
            $constraints = array('constraints' => 'mu_id=' . (int) $mu_id);
            $cache = R3Opt::getOptList('municipality_data', 'mu_id', 'mu_name_' . $lang, $constraints);
        }
        return $cache[$mu_id];
    }

    /**
     * Return the default municipality for the logged in user (from auth), or if the active municipality is 1 return this one
     *
     * return integer|null             the municipality code|null if more than 1
     */
    static public function getDefaultMunicipality($do_id = null) {
        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();

        if ($do_id === null) {
            $do_id = $auth->getDomainID();
        }
        $sql = "SELECT COUNT(*) FROM municipality WHERE do_id=" . (int) $do_id;
        if ($db->query($sql)->fetchColumn() == 1) {
            $sql = "SELECT mu_id FROM municipality WHERE do_id=" . (int) $do_id;
            return $db->query($sql)->fetchColumn();
        }
        return $auth->getParam('mu_id');
    }

    /**
     * Return the customer name from ID
     *
     * return string
     */
    static public function getCustomerNameFromID($do_id, $lang = null) {
        static $cache = null;

        if (!isset($cache[$do_id])) {
            $db = ezcDbInstance::get();
            $sql = "SELECT cus_name_1, cus_name_2 FROM customer WHERE do_id=" . (int) $do_id;
            $data = $db->query($sql)->fetch();
            $cache[$do_id] = array(1 => $data['cus_name_1'], 2 => $data['cus_name_2']);
        }
        if ($lang === null)
            $lang = R3Locale::getLanguageID();
        return $cache[$do_id][$lang];
    }

    /**
     * Return the fraction list for the given municipality
     */
    static public function getFractionList($do_id, $mu_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $opt = array_merge(array(
            'skip_empty' => false,
            'like' => null,
            'used_by' => null,
            'limit' => null,
            'constraints' => array('fraction_data.do_id=' . $db->quote($do_id),
                'fraction_data.mu_id=' . $db->quote($mu_id))), $opt);
        if ($opt['used_by'] <> '') {
            $opt['inner_join'] = array($opt['used_by'], 'fr_id', 'fr_id');
        }
        if ($opt['like'] == '') {
            return R3Opt::getOptList('fraction_data', 'fr_id', 'fr_name_' . R3Locale::getLanguageID(), $opt);
        } else {
            $like = trim($opt['like']);
            $limit = (int) $opt['limit'];
            $join = isset($opt['inner_join']) ? "INNER JOIN {$opt['inner_join'][0]} ON fraction_data.{$opt['inner_join'][1]}={$opt['inner_join'][0]}.{$opt['inner_join'][2]} " : '';
            $sql = "SELECT fraction_data.fr_id, fr_name_$lang AS fr_name " .
                    "FROM fraction_data " .
                    $join .
                    "WHERE do_id=" . (int) $do_id . " AND " .
                    "  fraction_data.mu_id=" . (int) $mu_id . " AND " .
                    "  fr_name_$lang ILIKE " . $db->quote("%{$like}%") . " " .
                    "ORDER BY fr_name_$lang ";
            if ($limit > 0)
                $sql .= "LIMIT $limit";
            $result = array();
            foreach ($db->query($sql) as $row) {
                if ($opt['skip_empty'] !== true || trim($row['fr_name']) <> '') {
                    $result[$row['fr_id']] = $row['fr_name'];
                }
            }
            return $result;
        }
    }

    /**
     * Return the fraction list for the given municipality
     */
    static public function getStreetList($do_id, $mu_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $do_id = (int) $do_id;
        $mu_id = (int) $mu_id;
        $opt = array_merge(array('skip_empty' => false, // Ignore empty name
            'like' => null,
            'used_by' => null,
            'limit' => null,
            'use_lookup_name' => false,
            'use_lkp_name' => false,
            'constraints' => array('street_data.do_id=' . (int) $do_id,
                'street_data.mu_id=' . (int) $mu_id)), $opt);
        if ($opt['used_by'] <> '') {
            $opt['inner_join'] = array($opt['used_by'], 'st_id', 'st_id');
        }

        $like = trim($opt['like']);
        $limit = (int) $opt['limit'];
        $join = isset($opt['inner_join']) ? "INNER JOIN {$opt['inner_join'][0]} ON street_data.{$opt['inner_join'][1]}={$opt['inner_join'][0]}.{$opt['inner_join'][2]} " : '';
        $fieldName = $opt['use_lkp_name'] ? "COALESCE(st_lkp_name_{$lang}, st_name_{$lang})" : "COALESCE(st_name_{$lang}, st_lkp_name_{$lang})";
        $sql = "SELECT street_data.st_id, {$fieldName} AS st_name
                FROM street_data
                {$join}
                WHERE do_id={$do_id} AND street_data.mu_id={$mu_id} AND st_name_{$lang} ILIKE " . $db->quote("%{$like}%") . " 
                GROUP BY street_data.st_id, {$fieldName}
                ORDER BY {$fieldName}, street_data.st_id ";
        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }
        $result = array();
        foreach ($db->query($sql) as $row) {
            if ($opt['skip_empty'] !== true || trim($row['st_name']) <> '') {
                $result[$row['st_id']] = $row['st_name'];
            }
        }
        return $result;
    }

    /**
     * Return the fraction list for the given municipality
     */
    static public function getCatMunicList($do_id, $mu_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $opt = array_merge(array(
            'constraints' => array(
                'do_id=' . $db->quote($do_id),
                'mu_id=' . $db->quote($mu_id))), $opt);
        return R3Opt::getOptList('cat_munic_data', 'cm_id', 'cm_name_' . R3Locale::getLanguageID(), $opt);
    }

    /**
     * Return the building type list for the given domain
     */
    static public function getBuildingTypeList($do_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $do_id = (int) $do_id;
        $opt = array_merge(array('mu_id' => null, 'used_by' => null), $opt);
        $table = 'building_type';
        $doIdRef = $table;
        if ($opt['used_by'] <> '') {
            $doIdRef = $opt['used_by'];
            $opt['inner_join'] = array($opt['used_by'], 'bt_id', 'bt_id');
        }

        $q = $db->createSelectQuery();
        $q->select("{$table}.bt_id, {$table}.bt_name_{$lang} AS bt_name, bt_has_extradata")
                ->from($table);
        if (isset($opt['inner_join'])) {
            $q->innerJoin($opt['inner_join'][0], $q->expr->eq("{$table}.{$opt['inner_join'][1]}", "{$opt['inner_join'][0]}.{$opt['inner_join'][2]}"));
        }
        $where = array("({$doIdRef}.do_id IS NULL OR {$doIdRef}.do_id={$do_id})");
        if ($opt['mu_id'] <> '') {
            $where['mu_id'] = 'mu_id=' . (int) $opt['mu_id'];
        }
        $q->where($where)
                ->groupBy("{$table}.bt_id, {$table}.bt_name_{$lang}, bt_has_extradata, bt_order")
                ->orderBy("bt_order, {$table}.bt_name_{$lang}, {$table}.bt_id");
        // echo $q;
        $stmt = $db->query($q);
        $result = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['bt_id']] = array('bt_name' => $row['bt_name'],
                'bt_has_extradata' => $row['bt_has_extradata'] == '' ? 'F' : 'T');
        }
        return $result;
    }

    /**
     * Return the building type list for the given domain
     */
    static public function getBuildingPurposeUseList($do_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $do_id = (int) $do_id;
        $opt = array_merge(array('mu_id' => null, 'used_by' => null), $opt);
        $table = 'building_purpose_use';
        $doIdRef = $table;
        if ($opt['used_by'] <> '') {
            $doIdRef = $opt['used_by'];
            $opt['inner_join'] = array($opt['used_by'], 'bpu_id', 'bpu_id');
        }

        $q = $db->createSelectQuery();
        $q->select("{$table}.bpu_id, {$table}.bpu_name_{$lang} AS bpu_name, bpu_has_extradata")
                ->from($table);
        if (isset($opt['inner_join'])) {
            $q->innerJoin($opt['inner_join'][0], $q->expr->eq("{$table}.{$opt['inner_join'][1]}", "{$opt['inner_join'][0]}.{$opt['inner_join'][2]}"));
        }
        $where = array("({$doIdRef}.do_id IS NULL OR {$doIdRef}.do_id={$do_id})");
        if ($opt['mu_id'] <> '') {
            $where['mu_id'] = 'mu_id=' . (int) $opt['mu_id'];
        }
        $q->where($where)
                ->groupBy("{$table}.bpu_id, {$table}.bpu_name_{$lang}, bpu_has_extradata, bpu_order")
                ->orderBy("bpu_order, {$table}.bpu_name_{$lang}, {$table}.bpu_id");
        // echo $q;
        $stmt = $db->query($q);
        $result = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['bpu_id']] = array('bpu_name' => $row['bpu_name'],
                'bpu_has_extradata' => $row['bpu_has_extradata'] == '' ? 'F' : 'T');
        }
        return $result;
    }

    /**
     * Return the building build year list for the given domain
     */
    static public function getBuildingBuildYearList($do_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $opt = array_merge(array('constraints' => 'do_id IS NULL OR do_id=' . $db->quote($do_id),
            'order' => 'bby_order, bby_name_' . R3Locale::getLanguageID()), $opt);
        return R3Opt::getOptList('building_build_year', 'bby_id', 'bby_name_' . R3Locale::getLanguageID(), $opt);
    }

    /**
     * Return the building restructure year list for the given domain
     */
    static public function getBuildingRestructureYearList($do_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $opt = array_merge(array('constraints' => 'do_id IS NULL OR do_id=' . $db->quote($do_id),
            'order' => 'bry_order, bry_name_' . R3Locale::getLanguageID()), $opt);
        return R3Opt::getOptList('building_restructure_year', 'bry_id', 'bry_name_' . R3Locale::getLanguageID(), $opt);
    }

    /**
     * Return the building usage hour list
     */
    static public function getBuildingUsageHourList($do_id, $isToTime) {
        $result = array();
        if (!$isToTime) {
            $result['00:00'] = '00:00';
        }
        $result['00:30'] = '00:30';
        for ($i = 1; $i < 24; $i++) {
            $k = sprintf('%02d:00', $i);
            $result[$k] = $k;
            $k = sprintf('%02d:30', $i);
            $result[$k] = $k;
        }
        if ($isToTime) {
            $result['24:00'] = '24:00';
        }
        return $result;
    }

    /**
     * Return the building usage day list
     */
    static public function getBuildingUsageDayList($do_id, array $opt = array()) {
        $result = array();
        for ($i = 1; $i <= 7; $i++) {
            $result[$i] = sprintf(_('%d su 7'), $i);
        }
        return $result;
    }

    static public function getEnergyZoneList($do_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $opt = array_merge(array('constraints' => 'do_id=' . $db->quote($do_id),
            'order' => 'ez_order, ez_code'), $opt);
        return R3Opt::getOptList('energy_zone_data', 'ez_id', 'ez_code', $opt);
    }

    static public function getEnergyClassList($do_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $opt = array_merge(array('constraints' => 'do_id=' . $db->quote($do_id),
            'order' => 'ec_order, ec_code'), $opt);
        return R3Opt::getOptList('energy_class_data', 'ec_id', 'ec_code', $opt);
    }

    static public function getEnergyClassLimitList($ez_id, $ec_id, $do_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $opt = array_merge(array('constraints' => 'do_id=' . $db->quote($do_id) . ' AND ez_id=' . (int) $ez_id . ' AND ec_id=' . (int) $ec_id,
            'order' => 'COALESCE(ecl_min, 0), COALESCE(ecl_max, 99999999)'), $opt);
        return R3Opt::getOptList('energy_class_limit_data', 'ecl_id', 'ecl_limit_text', $opt);
    }

    static public function getEnergySourceList($do_id, $et_code, array $opt = array()) {
        $data = array();
        $db = ezcDbInstance::get();
        $opt = array_merge(array('group_by' => true, 'order' => 'es_name_' . R3Locale::getLanguageID() . ', es_id'), $opt);
        $constraints = array("do_id={$do_id}", 'et_code=' . $db->quote($et_code));
        if (isset($opt['constraints'])) {
            if (is_string($opt['constraints'])) {
                $opt['constraints'] = array($opt['constraints']);
            }
            $constraints = array_merge($constraints, $opt['constraints']);
        }
        $opt['constraints'] = $constraints;
        $data[$do_id][$et_code] = R3Opt::getOptList('energy_source_data', 'es_id', 'es_name_' . R3Locale::getLanguageID(), $opt);
        return $data[$do_id][$et_code];
    }

    static public function getEnergyUDMListByEnergySource($do_id, $et_code, $es_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $constraints = array('et_code=' . $db->quote($et_code),
            'es_id=' . (int) $es_id);
        if (isset($opt['constraints'])) {
            if (is_string($opt['constraints'])) {
                $opt['constraints'] = array($opt['constraints']);
            }
            $constraints = array_merge($constraints, $opt['constraints']);
        }
        $opt['constraints'] = $constraints;
        $opt = array_merge(array('order' => 'udm_order, udm_name_' . R3Locale::getLanguageID()), $opt);
        return R3Opt::getOptList('ecogis.energy_source_udm_data', 'udm_id', 'udm_name_' . R3Locale::getLanguageID(), $opt);
    }

    /**
     * Return the energy-source-udm id by energy source id + udm id
     */
    static public function getEnergySourceUdmID($do_id, $es_id, $udm_id, $mu_id, $allowPrivate = false) {
        $db = ezcDbInstance::get();

        if (is_array($es_id)) {
            throw new exception('Invalid data type in getEnergySourceUdmID');
        }
        if (is_array($udm_id)) {
            throw new exception('Invalid data type in getEnergySourceUdmID');
        }

        // Ricava fonte del comune
        if ($mu_id > 0) {
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = 'es_id=' . (int) $es_id;
            $where[] = 'udm_id=' . (int) $udm_id;
            $where[] = 'do_id=' . (int) $do_id;
            $where[] = 'mu_id=' . (int) $mu_id;
            $where[] = 'esu_is_private IS FALSE';
            $q->select('esu_id')
                    ->from('ecogis.energy_source_udm')
                    ->where($where);
            $tot = 0;
            foreach ($db->query($q) as $row) {
                $esu_id = $row['esu_id'];
                $tot++;
            }
            if ($tot == 1) {
                return $esu_id;
            }
            if ($tot > 1) {
                throw new exception("Multiple source found in R3EcoGisHelper::getEnergySourceUdmID (do_id={$do_id}; es_id={$es_id}; udm_id={$udm_id})");
            }
        }

        // Ricava fonte globale
        $q = $db->createSelectQuery();
        $where = array();
        $where[] = 'es_id=' . (int) $es_id;
        $where[] = 'udm_id=' . (int) $udm_id;
        $where[] = 'do_id=' . (int) $do_id;
        $where[] = 'mu_id IS NULL';
        if (!$allowPrivate) {
            // biomass
            $where[] = 'esu_is_private IS FALSE';
        }
        $q->select('esu_id')
                ->from('ecogis.energy_source_udm')
                ->where($where);
        $tot = 0;
        foreach ($db->query($q) as $row) {
            $esu_id = $row['esu_id'];
            $tot++;
        }
        if ($tot == 1) {
            return $esu_id;
        }
        if ($tot > 1) {
            throw new exception("Multiple source found in R3EcoGisHelper::getEnergySourceUdmID (do_id={$do_id}; es_id={$es_id}; udm_id={$udm_id})");
        }
        return null;
    }

    static public function getElectricityEnergySourceUdmID($do_id, $mu_id) {
        $data = self::getEnergySourceAndUdm($do_id, 'ELECTRICITY');
        return self::getEnergySourceUdmID($do_id, $data['es_id'], $data['udm_id'], $mu_id);
    }

    static public function getElectricityCO2Factor($do_id, $mu_id) {
        $db = ezcDbInstance::get();
        $esu_id = self::getElectricityEnergySourceUdmID($do_id, $mu_id);
        if ($esu_id == '') {
            throw new Exception('Electricity energy-source not found');
        }
        $sql = "SELECT esu_co2_factor FROM ecogis.energy_source_udm WHERE esu_id={$esu_id}";
        return $db->query($sql)->fetchColumn();
    }

    /**
     * Return the energy-source-udm id by energy source id + udm id
     */
    static public function getMultipleEnergySourceUdmID($do_id, array $es_id, array $udm_id, $mu_id) {
        $db = ezcDbInstance::get();

        $data = array();
        for ($i = 0; $i < count($es_id); $i++) {
            // Ricava fonte del comune
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = 'es_id=' . (int) $es_id[$i];
            $where[] = 'udm_id=' . (int) $udm_id[$i];
            $where[] = 'do_id=' . (int) $do_id;
            $where[] = 'mu_id=' . (int) $mu_id;
            $where[] = 'esu_is_private IS FALSE';
            $q->select('esu_id')
                    ->from('ecogis.energy_source_udm')
                    ->where($where);
            $tot = 0;
            foreach ($db->query($q) as $row) {
                $data[$i] = $row['esu_id'];
                $tot++;
            }
            if ($tot > 1) {
                throw new exception("Multiple source found in R3EcoGisHelper::getMultipleEnergySourceUdmID (do_id={$do_id}; es_id={$es_id[$i]}; udm_id={$udm_id[$i]})");
            }

            // Ricava fonte globale
            if ($tot == 0) {
                $q = $db->createSelectQuery();
                $where = array();
                $where[] = 'es_id=' . (int) $es_id[$i];
                $where[] = 'udm_id=' . (int) $udm_id[$i];
                $where[] = 'do_id=' . (int) $do_id;
                $where[] = 'mu_id IS NULL';
                $where[] = 'esu_is_private IS FALSE';
                $q->select('esu_id')
                        ->from('ecogis.energy_source_udm')
                        ->where($where);
                foreach ($db->query($q) as $row) {
                    $data[$i] = $row['esu_id'];
                    $tot++;
                }
                if ($tot > 1) {
                    throw new exception("Multiple source found in R3EcoGisHelper::getMultipleEnergySourceUdmID (do_id={$do_id}; es_id={$es_id[$i]}; udm_id={$udm_id[$i]})");
                }
                if ($tot == 0) {
                    $data[$i] = null;
                }
            }
        }
        return $data;
    }

    /**
     * Return the energy-source-udm id by energy source id + udm id
     */
    static public function getEnergySourceUdmData($do_id, $es_id, $udm_id, $mu_id) {
        $db = ezcDbInstance::get();

        if (!is_array($es_id))
            $es_id = array($es_id);
        if (!is_array($udm_id))
            $udm_id = array($udm_id);

        // Ricava fonte del comune
        $data = array();
        for ($i = 0; $i < count($es_id); $i++) {
            if ($mu_id != null) {
                $q = $db->createSelectQuery();
                $where = array();
                $where[] = 'es_id=' . (int) $es_id[$i];
                $where[] = 'udm_id=' . (int) $udm_id[$i];
                $where[] = 'do_id=' . (int) $do_id;
                $where[] = 'mu_id=' . (int) $mu_id;
                $where[] = 'esu_is_private IS FALSE';
                $q->select('*')
                        ->from('ecogis.energy_source_udm_data')
                        ->where($where);
                // echo "$q\n";
                $tot = 0;
                foreach ($db->query($q, PDO::FETCH_ASSOC) as $row) {
                    $data[$i] = $row;
                    $tot++;
                }
                if ($tot > 1) {
                    throw new exception("Multiple source found in R3EcoGisHelper::getMultipleEnergySourceUdmID (do_id={$do_id}; es_id={$es_id[$i]}; udm_id={$udm_id[$i]})");
                }
            }

            // Ricava fonte globale
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = 'es_id=' . (int) $es_id[$i];
            $where[] = 'udm_id=' . (int) $udm_id[$i];
            $where[] = 'do_id=' . (int) $do_id;
            $where[] = 'mu_id IS NULL';
            $where[] = 'esu_is_private IS FALSE';
            $q->select('*')
                    ->from('ecogis.energy_source_udm_data')
                    ->where($where);
            // echo " $q\n";
            $tot = 0;
            foreach ($db->query($q, PDO::FETCH_ASSOC) as $row) {
                $data[$i] = $row;
                $tot++;
            }
            if ($tot > 1) {
                throw new exception("Multiple source found in R3EcoGisHelper::getMultipleEnergySourceUdmID (do_id={$do_id}; es_id={$es_id[$i]}; udm_id={$udm_id[$i]})");
            }
            if ($tot == 0) {
                $data[$i] = null;
            }
        }
        return $data;
    }

    /**
     * Return the municipality id by name of null if not found
     */
    static public function getMunicipalityIdByName($do_id, $mu_name, $mu_id = null) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        if ($mu_id == '') {
            $sql = "SELECT mu_id FROM municipality WHERE do_id={$do_id} AND mu_name_{$lang} ILIKE " . $db->quote($mu_name);
            $mu_id = $db->query($sql)->fetchColumn();
        }
        return $mu_id !== false ? $mu_id : null;
    }

    /**
     * Return the fraction id by name of null if not found
     */
    static public function getFractionIdByName($do_id, $mu_id, $fr_name, $fr_id = null) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        if ($fr_id == '') {
            $sql = "SELECT fr_id FROM common.fraction WHERE do_id={$do_id} AND mu_id=" . (int) $mu_id . " AND fr_name_$lang ILIKE " . $db->quote($fr_name);
            $fr_id = $db->query($sql)->fetchColumn();
        }
        return $fr_id !== false ? $fr_id : null;
    }

    /**
     * Return the municipality id by name of null if not found
     */
    static public function getStreetIdByName($do_id, $mu_id, $st_name, $st_id = null) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        if ($st_id == '') {
            $sql = "SELECT st_id FROM common.street WHERE do_id={$do_id} AND mu_id=" . (int) $mu_id . " AND st_name_$lang ILIKE " . $db->quote($st_name);
            $st_id = $db->query($sql)->fetchColumn();
        }
        return $st_id !== false ? $st_id : null;
    }

    /**
     * Return the device type list
     */
    static public function getDeviceTypeList($do_id, $et_code, array $opt = array()) {
        $db = ezcDbInstance::get();
        $q = $db->createSelectQuery();
        $lang = R3Locale::getLanguageID();
        $do_id = (int) $do_id;
        $more_where = " AND (do_id IS NULL OR do_id={$do_id}) ";
        if (isset($opt['production']))
            $more_where .= " AND dt_is_production='" . ($opt['production'] == true ? 'T' : 'F') . "'";
        if (isset($opt['consumption']))
            $more_where .= " AND dt_is_consumption='" . ($opt['consumption'] == true ? 'T' : 'F') . "'";
        $q->select("dt_id, dt_name_$lang AS dt_name, dt_has_extradata")
                ->from("device_type_data")
                ->where('et_code=' . $db->quote($et_code) . $more_where)
                ->orderBy("dt_order, dt_name_$lang, dt_id");
        $stmt = $db->query($q);
        $result = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['dt_id']] = array('dt_name' => $row['dt_name'],
                'dt_has_extradata' => $row['dt_has_extradata']);
        }
        return $result;
    }

    /**
     * Return the installation
     */
    static public function getDeviceInstallYearList($do_id, $et_code, array $opt = array()) {
        $result = array();
        for ($i = date('Y'); $i >= 1900; $i--) {
            $result["$i-01-01"] = $i;
        }
        return $result;
    }

    /**
     * Return the installation
     */
    static public function getDeviceEndYearList($do_id, $et_code, array $opt = array()) {
        $result = array();
        for ($i = date('Y'); $i >= 1900; $i--) {
            $result["$i-12-31"] = $i;
        }
        return $result;
    }

    /**
     * Return the installation
     */
    static public function getConsumptionYearList($do_id, $et_code, array $opt = array()) {
        $result = array();
        for ($i = date('Y'); $i >= date('Y') - 10; $i--) {
            $result["$i"] = $i;
        }
        return $result;
    }

    /**
     * Return a single meter data
     */
    static public function getMeterData($do_id, $em_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $q = $db->createSelectQuery()->select("*, em_name_$lang AS em_name, em_descr_$lang AS em_descr, es_name_$lang AS es_name, udm_name_$lang AS udm_name")
                ->from("energy_meter_data")
                ->where("em_id=" . (int) $em_id);
        return $db->query($q)->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Return an (dummy) energy meter by type and em_object_id
     */
    static public function getDummyEnergyMeter($type, $em_object_id) {
        $db = ezcDbInstance::get();
        $type = $db->quote($type);
        $em_object_id = (int) $em_object_id;
        $sql = "SELECT em_id FROM energy_meter em INNER JOIN energy_meter_object emo ON em.emo_id=emo.emo_id AND emo_code={$type} WHERE em_object_id={$em_object_id} LIMIT 1";
        return $db->query($sql)->fetchColumn();
    }

    /**
     * Add e (dummy) energy meter
     */
    static public function addDummyEnergyMeter($data, $emo_code_or_id) {
        $db = ezcDbInstance::get();
        if (is_string($emo_code_or_id))
            $emo_code_or_id = $db->query("SELECT emo_id FROM energy_meter_object WHERE emo_code=" . $db->quote($emo_code_or_id))->fetchColumn();
        $default = array('em_serial' => '', 'em_descr_1' => null, 'em_descr_2' => null, 'esu_id' => null, 'em_is_production' => false, 'up_id' => null, 'emo_id' => null);
        $data = array_merge($default, $data);
        $data['emo_id'] = $emo_code_or_id;
        foreach ($data as $key => $val) {
            if ($val === null) {
                $data[$key] = 'NULL';
            } else if ($val === true) {
                $data[$key] = 'TRUE';
            } else if ($val === false) {
                $data[$key] = 'FALSE';
            } else if (is_string($val)) {
                $data[$key] = $db->quote($val);
            }
        }
        $sql = "INSERT INTO energy_meter (" . implode(', ', array_keys($data)) . ") VALUES (" . implode(', ', $data) . ")";
        $db->exec($sql);
        return $db->lastInsertId('energy_meter_em_id_seq');
    }

    /**
     * Add e (dummy) energy meter
     */
    static public function getEnergyMeterObjectIdByCode($code) {
        $db = ezcDbInstance::get();
        return $db->query("SELECT emo_id FROM energy_meter_object WHERE emo_code=" . $db->quote($code))->fetchColumn();
    }

    /**
     * Add e (dummy) energy meter
     */
    static public function getDocumentTypeIdByCode($code) {
        $db = ezcDbInstance::get();
        return $db->query("SELECT doct_id FROM document_type WHERE doct_code=" . $db->quote($code))->fetchColumn();
    }

    /**
     * Return true if the object has an energy meter
     * Type -> BUILDING, STREETLIGHTING, ecc
     */
    static public function hasEnergyMeter($type, $id) {
        $db = ezcDbInstance::get();
        $type = $db->quote($type);
        $id = (int) $id;
        $sql = "SELECT COUNT(*)
                FROM energy_meter em 
                INNER JOIN energy_meter_object emo ON em.emo_id=emo.emo_id AND emo_code={$type} 
                WHERE em_object_id={$id}";
        return $db->query($sql)->fetchColumn() > 0;
    }

    /**
     * Return true if the object has a document
     * Type -> BUILDING, STREETLIGHTING, ecc
     */
    static public function hasDocument($type, $id) {
        $db = ezcDbInstance::get();
        $type = $db->quote($type);
        $id = (int) $id;
        $sql = "SELECT COUNT(*)
                FROM document doc 
                INNER JOIN document_type doct ON doc.doct_id=doct.doct_id AND doct_code={$type} 
                WHERE doc_object_id={$id}";
        return $db->query($sql)->fetchColumn() > 0;
    }

    static public function hasGlobalPlainRowHasGauge($id) {
        $db = ezcDbInstance::get();
        $id = (int) $id;
        $sql = "SELECT COUNT(*)
                FROM ecogis.global_plain_gauge gpg
                INNER JOIN ecogis.global_plain_row gpr ON gpr.gpr_id=gpg.gpr_id
                WHERE gp_id={$id}";
        return $db->query($sql)->fetchColumn() > 0;
    }

    /**
     * Add e (dummy) energy meter
     */
    static public function getDocumentTypeByDocumentId($doc_id) {
        $db = ezcDbInstance::get();
        return $db->query("SELECT doct_id FROM document doc WHERE doc_id=" . (int) $doc_id)->fetchColumn();
    }

    /**
     * Return the work status list
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the municipality list
     */
    static public function getWorkStatusList($do_id) {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $q = $db->createSelectQuery()
                ->select("ws_id, ws_name_$lang AS ws_name, ws_class")
                ->from("work_status")
                ->orderBy("ws_order, ws_name, ws_id");
        $result = array();
        foreach ($db->query($q) as $row) {
            $result[$row['ws_id']] = array('name' => $row['ws_name'], 'class' => $row['ws_class']);
        }
        return $result;
    }

    /**
     * Return the filter for the action catalog
     */
    static public function getCategoriesListTreeForFilter($do_id, $filterFor) {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $space = '&nbsp;&nbsp;&nbsp;&nbsp;';
        $do_id = (int) $do_id;
        if ($filterFor == 'action_catalog') {
            $sql = "WITH RECURSIVE recursetree(level, id, gc_id, gc_parent_id, path, name, gc_order) AS (
                      SELECT DISTINCT 1, 'M' || gc_main.gc_id::text, gc_main.gc_id, gc_main.gc_parent_id, gc_main.gc_name_{$lang}, gc_main.gc_name_{$lang}, gc_main.gc_order*1000::bigint
                      FROM ecogis.global_category gc_main
                      INNER JOIN ecogis.global_category gc1 ON gc1.gc_parent_id=gc_main.gc_id
                      INNER JOIN ecogis.action_catalog ac1 ON ac1.gc_id=gc1.gc_id
                      INNER JOIN ecogis.municipality mu1 ON ac1.mu_id=mu1.mu_id AND mu1.do_id={$do_id}
                      WHERE gc_main.gc_parent_id IS NULL
                    UNION ALL
                      SELECT DISTINCT rt.level+1, 'D' || gc2.gc_id::text, gc2.gc_id, gc2.gc_parent_id, '{$space}' || gc2.gc_name_{$lang}, gc2.gc_name_{$lang}, rt.gc_order+gc2.gc_order
                      FROM ecogis.global_category gc2
                      INNER JOIN ecogis.action_catalog ac2 ON ac2.gc_id=gc2.gc_id
                      INNER JOIN ecogis.municipality mu2 ON ac2.mu_id=mu2.mu_id AND mu2.do_id={$do_id}
                      INNER JOIN recursetree rt ON rt.gc_id = gc2.gc_parent_id
                )
                SELECT level, id, path FROM recursetree 
                ORDER BY gc_order, gc_parent_id nulls first, path";
        } else if ($filterFor == 'global_plain_action') {
            $sql = "WITH RECURSIVE recursetree(level, id, gc_id, gc_parent_id, path, name, gc_order) AS (
                      SELECT DISTINCT 1, 'M' || gc_main.gc_id::text, gc_main.gc_id, gc_main.gc_parent_id, gc_main.gc_name_{$lang}, gc_main.gc_name_{$lang}, gc_main.gc_order*1000::bigint
                      FROM ecogis.global_category gc_main
                      INNER JOIN ecogis.global_category gc1 ON gc1.gc_parent_id=gc_main.gc_id
                      INNER JOIN ecogis.global_plain_row gpr1 ON gpr1.gc_id=gc1.gc_id
                      INNER JOIN ecogis.global_plain gp1 ON gp1.gp_id=gpr1.gp_id
                      INNER JOIN ecogis.municipality mu1 ON gp1.mu_id=mu1.mu_id AND mu1.do_id={$do_id}
                      WHERE gc_main.gc_parent_id IS NULL
                    UNION ALL
                      SELECT DISTINCT rt.level+1, 'D' || gc2.gc_id::text, gc2.gc_id, gc2.gc_parent_id, '{$space}' || gc2.gc_name_{$lang}, gc2.gc_name_{$lang}, rt.gc_order+gc2.gc_order
                      FROM ecogis.global_category gc2
                      
                      INNER JOIN ecogis.global_plain_row gpr2 ON gpr2.gc_id=gc2.gc_id
                      INNER JOIN ecogis.global_plain gp2 ON gp2.gp_id=gpr2.gp_id
                      INNER JOIN ecogis.municipality mu2 ON gp2.mu_id=mu2.mu_id AND mu2.do_id={$do_id}
                      INNER JOIN recursetree rt ON rt.gc_id = gc2.gc_parent_id
                )
                SELECT level, id, path FROM recursetree 
                ORDER BY gc_order, gc_parent_id nulls first, path";
        } else {
            throw new Exception("Invalid parameter \"{$filterFor}\"");
        }
        $result = array();
        foreach ($db->query($sql) as $row) {
            $result[$row['id']] = array('level' => $row['level'], 'id' => $row['id'], 'name' => $row['path']);
        }
        return $result;
    }

    /**
     * Return the filter for the action catalog
     */
    static public function getGlobalplainActionListForFilter($do_id, $filterFor) {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $do_id = (int) $do_id;
        if ($filterFor == 'action_catalog') {
            $sql = "SELECT DISTINCT gpa_name_{$lang} AS gpa_name 
                    FROM ecogis.action_catalog_data 
                    WHERE do_id={$do_id} 
                    ORDER BY gpa_name";
        } else if ($filterFor == 'global_plain_action') {
            $sql = "SELECT DISTINCT gpa_name_1 AS gpa_name
                    FROM ecogis.global_plain_action gpa 
                    INNER JOIN ecogis.global_plain_row gpr ON gpa.gpa_id=gpr.gpa_id 
                    INNER JOIN ecogis.global_plain gp ON gp.gp_id=gpr.gp_id 
                    INNER JOIN ecogis.municipality mu ON gp.mu_id=mu.mu_id AND mu.do_id={$do_id} 
                    ORDER BY gpa_name";
        } else {
            throw new Exception("Invalid parameter \"{$filterFor}\"");
        }
        $result = array();
        foreach ($db->query($sql) as $row) {
            $result[$row['gpa_name']] = $row['gpa_name'];
        }
        return $result;
    }

    /**
     * Return the work type list
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the municipality list
     */
    static public function getWorkTypeList($do_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $do_id = (int) $do_id;
        $q = $db->createSelectQuery()
                ->select("master_wt_id, wt_id, master_wt_name_$lang AS master_wt_name, wt_has_extradata, " .
                        "wt_name_$lang AS wt_name, wt_class, wt_message_$lang AS wt_message, wt_save_primary, wt_save_electricity")
                ->from("work_type_data")
                ->where("(do_id IS NULL OR do_id={$do_id})")
                ->orderBy("master_wt_order, wt_order, master_wt_name, wt_name, wt_id");
        $stmt = $db->query($q);
        $result = array();
        $detail = array();
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['master_wt_id']] = array('name' => $row['master_wt_name'],
                'class' => $row['wt_class']);
            $detail[$row['master_wt_id']][$row['wt_id']] = array('name' => $row['wt_name'],
                'has_extradata' => $row['wt_has_extradata'] ? 'T' : 'F',
                'save_primary' => $row['wt_save_primary'] ? 'T' : 'F',
                'save_electricity' => $row['wt_save_electricity'] ? 'T' : 'F',
                'message' => $row['wt_message']);
        }
        foreach ($detail as $key => $value) {
            $result[$key]['detail'] = $value;
        }
        return $result;
    }

    /**
     * Return the work type list
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the municipality list
     */
    static public function getWorkFundingTypeList($do_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $q = $db->createSelectQuery()
                ->select("ft_id, ft_name_$lang AS ft_name, ft_has_extradata, ft_class")
                ->from("funding_type")
                ->where("(do_id IS NULL OR do_id={$do_id})")
                ->orderBy("ft_order, ft_name, ft_id");
        $result = array();
        foreach ($db->query($q) as $row) {
            $result[$row['ft_id']] = array('name' => $row['ft_name'], 'has_extradata' => $row['ft_has_extradata'] ? 'T' : 'F', 'class' => $row['ft_class']);
        }
        return $result;
    }

    // Restituisce es_id e udm_id
    static public function getEnergySourceAndUdm($do_id, $et_code) {
        if ($et_code == 'ELECTRICITY') {
            return array('es_id' => 14, 'udm_id' => 3);  // Electricity  (kwh)
        } else {
            return array('es_id' => 12, 'udm_id' => 4);  // Thermal electricity
        }
    }

    /**
     * Return the work type list
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the municipality list
     */
    static public function getGlobalPlainGaugeUdmList($do_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $q = $db->createSelectQuery()
                ->select("gpgu_id, gpgu_name_$lang AS gpgu_name")
                ->from("ecogis.global_plain_gauge_udm")
                ->orderBy("gpgu_order, gpgu_name");
        return $db->query($q)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
    }

    static public function getGlobalPlainGaugeTypeList($do_id, $gpr_id = null) {
        $result = array('F' => _('Riduzione energetica'), 'T' => _('Produzione energetica'));
        if ($gpr_id != null) {
            $db = ezcDbInstance::get();
            $sql = "SELECT gpr_expected_energy_saving, gpr_expected_renewable_energy_production FROM ecogis.global_plain_row where gpr_id=" . (int) $gpr_id;
            $data = $db->query($sql)->Fetch(PDO::FETCH_ASSOC);
            if (empty($data['gpr_expected_energy_saving'])) {
                unset($result['F']);
            }
            if (empty($data['gpr_expected_renewable_energy_production'])) {
                unset($result['T']);
            }
        }
        return $result;
    }

    static public function getGlobalPlainActionTypeList($do_id) {
        $result = array('G' => _('Indicatore'), 'P' => _('Percentuale'));
        return $result;
    }

    /**
     * Return the primary energy source for the work
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the municipality list
     */
    static public function getWorkPrimaryEnergySource($do_id, $bu_id, $addElectricity) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $q = $db->createSelectQuery()
                ->select("esu_id, esu_name_$lang AS esu_name, udm_name_$lang AS udm_name")
                ->from("work_energy_source_primary_data")
                ->where("do_id={$do_id} AND bu_id=" . (int) $bu_id)
                ->orderBy("esu_name, esu_id");
        $result = array();
        foreach ($db->query($q) as $row) {
            $result[$row['esu_id']] = array('name' => $row['esu_name'], 'udm' => $row['udm_name']);
        }
        if ($addElectricity) {
            $esu = self::getEnergySourceAndUdm($do_id, 'HEATING');
            $sql = "SELECT mu_id, esu_id, es_name_$lang || ' (' || udm_name_$lang || ')' AS esu_name, udm_name_$lang AS udm_name
                    FROM ecogis.energy_source_udm_data
                    WHERE es_id={$esu['es_id']} AND udm_id={$esu['udm_id']} AND do_id={$do_id} 
                          AND esu_is_private IS FALSE AND esu_is_consumption IS TRUE
                    ORDER BY COALESCE(mu_id, 0) DESC
                    LIMIT 1";
            foreach ($db->query($sql) as $row) {
                $result[$row['esu_id']] = array('name' => $row['esu_name'], 'udm' => $row['udm_name']);
            }
        }
        return $result;
    }

    /**
     * Return the electricity energy source for the work
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the municipality list
     */
    static public function getWorkElectricityEnergySource($do_id, $bu_id, $addElectricity) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $q = $db->createSelectQuery()
                ->select("esu_id, esu_name_$lang AS esu_name, udm_name_$lang AS udm_name")
                ->from("work_energy_source_electricity_data")
                ->where("do_id={$do_id} AND bu_id=" . (int) $bu_id)
                ->orderBy("esu_name, esu_id");
        $result = array();
        foreach ($db->query($q) as $row) {
            $result[$row['esu_id']] = array('name' => $row['esu_name'], 'udm' => $row['udm_name']);
        }
        if ($addElectricity) {
            $esu = self::getEnergySourceAndUdm($do_id, 'ELECTRICITY');
            $sql = "SELECT mu_id, esu_id, es_name_$lang || ' (' || udm_name_$lang || ')' AS esu_name, udm_name_$lang AS udm_name
                    FROM ecogis.energy_source_udm_data
                    WHERE es_id={$esu['es_id']} AND udm_id={$esu['udm_id']} AND do_id={$do_id}
                          AND esu_is_private IS FALSE AND esu_is_consumption IS TRUE
                    ORDER BY COALESCE(mu_id, 0) DESC
                    LIMIT 1";
            foreach ($db->query($sql) as $row) {
                $result[$row['esu_id']] = array('name' => $row['esu_name'], 'udm' => $row['udm_name']);
            }
        }
        return $result;
    }

    /**
     * Return the utility supplier energy source for the work
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the municipality list
     */
    static public function getUtilitySupplierList($do_id, $mu_id, $kind) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $mu_id = (int) $mu_id;
        $sql = "SELECT us_id, us_name_$lang AS us_name
                FROM utility_product_data
                WHERE et_code=" . $db->quote($kind) . " AND
                      do_id={$do_id} AND mu_id={$mu_id}
                GROUP BY us_id,us_name_$lang
                ORDER BY us_name_$lang ";
        $result = array();
        foreach ($db->query($sql) as $row) {
            $result[$row['us_id']] = $row['us_name'];
        }
        return $result;
    }

    /**
     * Return the electricity energy source for the work
     *
     * param integer|null $do_id        the user domain. If null no domain filter
     * return array                     the municipality list
     */
    static public function getUtilityProductList($do_id, $us_id, $kind) {
        $db = ezcDbInstance::get();
        $opt = array('show_query' => false,
            'constraints' => array('us_id=' . (int) $us_id, 'et_code=' . $db->quote($kind)));
        return R3Opt::getOptList('utility_product_data', 'up_id', 'up_name_' . R3Locale::getLanguageID(), $opt);
    }

    /**
     * Return the if a customer is owner of a municipaliry
     * param integer mu_id     Municipality code
     * param integer do_id     Domain id. Default authenticated user domain
     */
    static public function isValidMunicipality($mu_id, $do_id = null) {
        static $cache = null;
        $auth = R3AuthInstance::get();
        if ($auth->hasPerm('SHOW', 'ALL_DOMAINS'))
            return true;
        if ($do_id === null) {
            $do_id = $auth->getDomainID();
        }
        if (!isset($cache[$do_id])) {
            $db = ezcDbInstance::get();
            $q = $db->createSelectQuery();
            $q->select('mu_id')
                    ->from('municipality')
                    ->where('do_id=' . (int) $do_id)
                    ->orderBy('mu_id');
            $cache[$do_id] = $db->query($q)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
        }
        return isset($cache[$do_id][$mu_id]);
    }

    /**
     * Return the building data
     * param integer bu_id     Building id
     * param integer do_id     Domain id. Default authenticated user domain
     */
    static public function getBuildingData($bu_id, $do_id = null) {
        static $cache = null;
        $auth = R3AuthInstance::get();
        if ($do_id === null) {
            $do_id = $auth->getDomainID();
        }
        if (!isset($cache[$do_id][$bu_id])) {
            $db = ezcDbInstance::get();
            $q = $db->createSelectQuery();
            $q->select('*')
                    ->from('building_data')
                    ->where('bu_id=' . (int) $bu_id);
            $cache[$do_id][$bu_id] = $db->query($q)->fetch(PDO::FETCH_ASSOC);
        }
        return $cache[$do_id][$bu_id];
    }

    static public function getChangeLogData($table, $row_id) {
        $db = ezcDbInstance::get();
        if (strpos($table, '.') === false) {
            $schema = 'ecogis'; // Get from DB connection
        } else {
            list($schema, $table) = explode('.', $table);
        }
        $sql = "SELECT get_log_table(" . $db->quote($schema) . ", " . $db->quote($table) . ")";
        $clt_id = (int) $db->query($sql)->fetchColumn();
        $sql = "SELECT cl_user, cl_action, cl_timestamp, us_login, us_name
                FROM ecogis.change_log  cl
                LEFT JOIN auth.users us ON cl.cl_user=us.us_id
                WHERE clt_id={$clt_id} AND cl_row_id=" . (int) $row_id;
        $result = array('ins_user_id' => null, 'ins_user_login' => null, 'ins_user_name' => null, 'ins_user' => null, 'ins_date' => null, 'ins_date_fmt' => null,
            'mod_user_id' => null, 'mod_user_login' => null, 'mod_user_name' => null, 'mod_user' => null, 'mod_date' => null, 'mod_date_fmt' => null);
        $result['last_change_time'] = null;
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            if ($row['cl_action'] == 'I') {
                $result['ins_user_id'] = $row['cl_user'];
                $result['ins_user_login'] = $row['us_login'];
                $result['ins_user'] = $result['ins_user_name'] = $row['us_name'];
                $result['ins_date'] = $row['cl_timestamp'];
                $result['ins_date_fmt'] = SQLDateToStr($row['cl_timestamp'], 'd/m/Y H:i');
                $result['last_change_time'] = SQLDateToStr($row['cl_timestamp'], 'YmdHis');
            } else if ($row['cl_action'] == 'U') {
                $result['mod_user_id'] = $row['cl_user'];
                $result['mod_user_login'] = $row['us_login'];
                $result['mod_user'] = $result['mod_user_name'] = $row['us_name'];
                $result['mod_date'] = $row['cl_timestamp'];
                $result['mod_date_fmt'] = SQLDateToStr($row['cl_timestamp'], 'd/m/Y H:i');
                $result['last_change_time'] = SQLDateToStr($row['cl_timestamp'], 'YmdHis');
            }
        }
        return $result;
    }

    /*
     * Return geographic informations
     */

    static public function getGeoInfo($do_id, $param = null) {
        static $data = array();
        if (!isset($data[$do_id])) {
            $db = ezcDbInstance::get();
            $sql = "SELECT cus.do_id, dn_name, cus_map_copyright_1, cus_map_copyright_2, cus.cus_srid
                    FROM ecogis.customer cus
                    INNER JOIN auth.domains_name dn ON cus.do_id = dn.do_id AND dn.dn_type='N'
                    WHERE cus.do_id=" . (int) $do_id;
            $row = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
            $row['cus_schema'] = R3_IS_MULTIDOMAIN ? $schema = 'geo_' . strtolower($row['dn_name']) : 'geo';
            $data[$do_id] = $row;
        }
        if ($param === null)
            return $data[$do_id];
        if (isset($data[$do_id][$param]))
            return $data[$do_id][$param];
        return null;
    }

    /**
     * Return the sub-domain name from the request
     * @param string         $_SERVER['HTTP_REQUEST']
     * @param type           if true validate the name from the the auth. system
     * @return string 
     */
    static public function getSubDomainName($httpHostName, $validate) {
        if (defined('R3_2ND_LEVEL_DOMAIN')) {
            return substr($httpHostName, 0, -strlen(R3_2ND_LEVEL_DOMAIN) - 1);
        }
        $oldPart = explode('.', $httpHostName);
        $newPart = array();
        for ($i = 0; $i < count($oldPart) - 2; $i++) {
            $newPart[] = $oldPart[$i];
        }
        return implode('.', $newPart);
    }

    /**
     * Return the geo schema name
     */
    static public function getGeoSchema($do_id = null) {
        if ($do_id === null) {
            $do_id = $_SESSION['do_id'];
        }
        return R3EcoGisHelper::getGeoInfo($do_id, 'cus_schema');
    }

    /**
     * Return the geo schema name
     */
    static public function getTableSRID($table, $schema = 'public', $geoCol = 'the_geom') {
        $table = strTolower($table);
        if (strpos($table, '.') !== false) {
            list($schema, $table) = explode('.', $table);
        }
        $sql = "SELECT srid
                FROM public.geometry_columns
                WHERE f_table_catalog='' AND f_table_schema='{$schema}' AND f_table_name='{$table}' AND f_geometry_column='{$geoCol}'";
        return ezcDbInstance::get()->query($sql)->fetchColumn();
    }

    static public function getMapPreviewURL($do_id, $object, $object_id, $lang, $autoGenerate = true) {
        ezcLog::getInstance()->log(__METHOD__ . "({$do_id}, {$object}, {$object_id}, {$lang}, {$autoGenerate}): called", ezcLog::DEBUG);
        $objectLC = strtoupper($object);
        $do_id = (int) $do_id;
        $object_id = (int) $object_id;
        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        $tollerance = $auth->getConfigValue('APPLICATION', 'MAP_PREVIEW_TOLLERANCE', '20%');
        // Map in cache?
        $sql = "SELECT ca_id, ca_object_id, cat_code, ca_expire_time, CASE WHEN ca_expire_time<CURRENT_TIMESTAMP THEN 'T' END AS cat_expired,
                       xmin(the_geom) AS x1, ymin(the_geom) AS y1, xmax(the_geom) AS x2, ymax(the_geom) AS y2
                FROM cache ca
                INNER JOIN cache_type cat ON ca.cat_id=cat.cat_id
                WHERE ca_object_id=" . (int) $object_id . " AND ca_lang=" . (int) $lang . " AND ca_tollerance=" . $db->quote($tollerance);
        $data = $db->query($sql)->fetch(PDO::FETCH_ASSOC);

        // Check if cache is expired
        if ($data !== false && $data['cat_expired'] == 'T') {
            ezcLog::getInstance()->log(__METHOD__ . ": Cache expired", ezcLog::INFO);
            R3EcoGisCacheHelper::purgeMapPreviewCache($do_id, $object_id);
            $data = false;
        }
        // Check if cache is inconsistent (no file)
        $fileName = R3EcoGisCacheHelper::getCachedFileName($object, $object_id, $lang, $tollerance);
        if (!file_exists($fileName)) {
            ezcLog::getInstance()->log(__METHOD__ . ": Missing cache", ezcLog::INFO);
            R3EcoGisCacheHelper::resetMapPreviewCache($do_id, $object_id);
            $data = false;
        }

        $url = "edit.php?on=map_preview&act=open&layer={$object}&object_id={$object_id}&file_id={$data['ca_id']}&lang={$lang}&tollerance={$tollerance}&";
        ezcLog::getInstance()->log(__METHOD__ . ": Cache OK. URL: {$url}", ezcLog::DEBUG);
        return $url;
    }

    /**
     * 
     */
    static function cleanTmporaryMapEditingData() {
        $temporaryTables = array('edit_tmp_point', 'edit_tmp_linestring', 'edit_tmp_polygon');

        $db = ezcDbInstance::get();
        foreach ($temporaryTables as $table) {
            $sql = "DELETE FROM {$table} WHERE mod_date<=NOW()-INTERVAL '3 days'";
            $db->exec($sql);
        }
    }

    /**
     * Generate the preview map
     */
    static function generateMapPreview($layer, $key, $lang, $tollerance = '10%') {
        require_once R3_LIB_DIR . 'maplib.php';
        require_once R3_LIB_DIR . 'custom.map.php';
        global $languages;

        ezcLog::getInstance()->log(__METHOD__ . "({$layer}, {$key}, {$lang}, {$tollerance}) called", ezcLog::DEBUG);

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();

        list($width, $height) = explode('x', $auth->getConfigValue('APPLICATION', 'MAP_PREVIEW_SIZE', '200x200'));
        $cus_schema = R3EcoGisHelper::getGeoSchema();
        $domain_name = strtolower(R3EcoGisHelper::getDomainCodeFromID($_SESSION['do_id']));
        $mapfileDir = R3_CONFIG_DIR . $domain_name . '/map/';
        $mapPrev = new mapPreview($mapfileDir, $languages[$lang], $width, $height);
        if (function_exists('custom_map_edit_map_file')) {
            custom_map_edit_map_file($mapPrev->map);
        }
        switch ($layer) {
            case 'building':
                $opt = $auth->getConfigValue('APPLICATION', 'BUILDING_TABLE');
                $options['outlinecolor'] = isset($opt['outlinecolor']) ? $opt['outlinecolor'] : array();
                $sql = "SELECT ST_Extent(the_geom) FROM building WHERE bu_id=" . (int) $key;
                $mapPrev->highlight('ecogis_building_outline_selected', "the_geom FROM (SELECT * FROM {$cus_schema}.building WHERE bu_id=" . (int) $key . ") AS foo USING UNIQUE bu_id ", $options);
                break;
            case 'edit_building':
                // Edit building
                $opt = $auth->getConfigValue('APPLICATION', 'BUILDING_TABLE');
                $options['outlinecolor'] = isset($opt['outlinecolor']) ? $opt['outlinecolor'] : array();
                $sql = "SELECT ST_Extent(the_geom) FROM edit_tmp_polygon WHERE session_id=" . $db->quote($key);
                $mapPrev->highlight('ecogis_building_outline_selected', "the_geom FROM (SELECT gid AS bu_id, the_geom FROM ecogis.edit_tmp_polygon WHERE session_id=" . $db->quote($key) . ") AS foo USING UNIQUE bu_id ", $options);
                break;
            case 'street_lighting':
                $opt = $auth->getConfigValue('APPLICATION', 'STREET_LIGHTING_TABLE');
                $options['outlinecolor'] = isset($opt['outlinecolor']) ? $opt['outlinecolor'] : array();
                $sql = "SELECT ST_Extent(the_geom) FROM street_lighting WHERE sl_id=" . (int) $key;
                $mapPrev->highlight('ecogis_street_lighting_outline_selected', "the_geom FROM (SELECT * FROM {$cus_schema}.street_lighting WHERE sl_id=" . (int) $key . ") AS foo USING UNIQUE sl_id ", $options);
                break;
            case 'edit_street_lighting':
                $opt = $auth->getConfigValue('APPLICATION', 'BUILDING_TABLE');
                $options['outlinecolor'] = isset($opt['outlinecolor']) ? $opt['outlinecolor'] : array();
                $sql = "SELECT ST_Extent(the_geom) FROM edit_tmp_polygon WHERE session_id=" . $db->quote($key);
                $mapPrev->highlight('ecogis_street_lighting_outline_selected', "the_geom FROM (SELECT gid AS sl_id, the_geom FROM ecogis.edit_tmp_polygon WHERE session_id=" . $db->quote($key) . ") AS foo USING UNIQUE sl_id ", $options);
                break;
            default:
                $this->deliverError(sprintf(_("Il layer \"$this->layer\" non e' valido")));
                die();
        }
        $the_geom = $db->query($sql)->fetchColumn(0);
        if ($the_geom != '') {
            $extentArr = array();
            $extentArr = ST_FetchBox($the_geom);
            $extentArr['geox1'] = $extentArr[0];
            $extentArr['geoy1'] = $extentArr[1];
            $extentArr['geox2'] = $extentArr[2];
            $extentArr['geoy2'] = $extentArr[3];
            $deltaX = $deltaY = $tollerance;
            $layer = @$mapPrev->map->getLayerByName('comuni_overlay');
            if ($layer) {
                $class0 = @$layer->getClass(0);
                if ($class0) {
                    $class0->setExpression("('[istat]' != '" . $vlu['mu_id'] . "')");
                }
            }
            $PrevFile = $mapPrev->getMapImgByBox($extentArr['geox1'], $extentArr['geoy1'], $extentArr['geox2'], $extentArr['geoy2'], max($deltaX, $deltaY));
            return $PrevFile;
        }
        return null;
    }

    static public function getGlobalCategoryMainList($do_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $q = $db->createSelectQuery();
        $q->select("gc_id, gc_name_{$lang} AS gc_name")
                ->from('ecogis.global_category')
                ->where('gc_parent_id IS NULL')
                ->orderBy('gc_order');
        return $db->query($q)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
    }

    static public function getStatisticMainList($do_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $q = $db->createSelectQuery();
        $q->select("st_id, st_title_short_{$lang} AS st_title_short")
                ->from('ecogis.stat_type')
                ->where('st_parent_id IS NULL')
                ->orderBy('st_order, st_title_short');
        return $db->query($q)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
    }

    /*
     * Macrocategoria e categoria PAES per l'edificio dato
     *
     * @param integer $bu_id
     */

    static public function getGlobalCategoryForActionCatalogBuilding($bu_id) {
        static $data = array();

        if (isset($data[$bu_id])) {
            return $data[$bu_id];
        }
        $db = ezcDbInstance::get();
        $result = array(0 => null, 1 => null);
        $q = $db->createSelectQuery();
        $q->select("acsc.gc_id, gc_parent_id")
                ->from('action_catalog_sub_category acsc')
                ->innerJoin('global_category c', 'acsc.gc_id=c.gc_id')
                ->where(array("emo_code='BUILDING'", "id=" . (int) $bu_id));
        $tot = 0;
        foreach ($db->query($q) as $row) {
            $result[0] = $row['gc_id'];
            $result[1] = $row['gc_parent_id'];
            $tot++;
        }
        if ($tot <= 1) {
            return $result;
        }
        $data[$bu_id] = $result;
        throw new exception("Multiple categories for this building in R3EcoGisHelper::getGlobalCategoryForActionCatalogBuilding (bu_id={$bu_id})");
    }

    /*
     * Restituisce la macrocategoria, categoria e nome edificio
     *
     * @param integer $bu_id
     */

    static public function getGlobalCategoryPathForActionCatalogBuilding($bu_id, $lang, $separator = ' > ') {

        if ($bu_id == '') {
            return null;
        }
        $bu_id = (int) $bu_id;

        $db = ezcDbInstance::get();
        $q = $db->createSelectQuery();
        $q->select("gc2.gc_name_{$lang} AS gc_name_parent, gc1.gc_name_{$lang} AS gc_name, bu_name_{$lang} AS bu_name")
                ->from('action_catalog_sub_category acsc')
                ->innerJoin('global_category gc1', 'acsc.gc_id=gc1.gc_id')
                ->innerJoin('global_category gc2', 'gc1.gc_parent_id=gc2.gc_id')
                ->innerJoin('building bu', 'acsc.id=bu.bu_id')
                ->where(array("emo_code='BUILDING'", "id=" . (int) $bu_id));
        $data = $db->query($q)->fetch(PDO::FETCH_ASSOC);
        if ($data === false) {
            return null;
        }
        return implode($separator, $data);
    }

    static function getStatGeneralData() {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $result = array();
        $sql = "SELECT *, sg_title_{$lang} AS sg_title, sg_upper_text_{$lang} AS sg_upper_text, sg_lower_text_{$lang} AS sg_lower_text
                FROM ecogis.stat_general";
        $vlu = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        if ($vlu === false) {
            $vlu = array();
        }
        return $vlu;
    }

    static function getUploadedFile($name, $id = 0) {
        if (isset($_FILES[$name])) {
            if (is_array($_FILES[$name]['name'])) {
                // Consider only the first file
                $files = array(
                    'name' => $_FILES[$name]['name'][$id],
                    'type' => $_FILES[$name]['type'][$id],
                    'tmp_name' => $_FILES[$name]['tmp_name'][$id],
                    'error' => $_FILES[$name]['error'][$id],
                    'size' => $_FILES[$name]['size'][$id]);
            } else {
                $files = array(
                    'name' => $_FILES[$name]['name'],
                    'type' => $_FILES[$name]['type'],
                    'tmp_name' => $_FILES[$name]['tmp_name'],
                    'error' => $_FILES[$name]['error'],
                    'size' => $_FILES[$name]['size']);
            }
        } else {
            $files = array(
                'name' => null,
                'type' => null,
                'tmp_name' => null,
                'error' => UPLOAD_ERR_NO_FILE);
        }
        return $files;
    }

}

/**
 * Utility function class for EcoGIS
 */
class R3EcoGisCalculatorHelper {

    /**
     * Global energy cost
     */
    static private $energySourceUDMData = null;

    static private function cacheEnergySourceUDM($do_id) {
        $db = ezcDbInstance::get();
        $sql = "SELECT * FROM ecogis.energy_source_udm_default WHERE do_id=" . (int) $do_id;
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            R3EcoGisCalculatorHelper::$energySourceUDMData[$row['esu_id']] = R3Locale::convert2PHP($row);
        }
    }

    /**
     * Cache all the energy cost
     */
    static private function cacheEnergyCost($do_id) {
        R3EcoGisCalculatorHelper::cacheEnergySourceUDM($do_id);
    }

    /**
     * Return cost
     *
     * @see getNetEstimatedCost or getNetEffectiveCost
     */
    static private function getNetCost(array $data, $returnAsLocale, $kind) {
        $result = null;
        if (array_key_exists("wo_{$kind}_cost", $data) && array_key_exists("wo_{$kind}_contribution", $data) &&
                ($data["wo_{$kind}_cost"] != '' || $data["wo_{$kind}_contribution"] != '')) {
            $result = $data["wo_{$kind}_cost"] - $data["wo_{$kind}_contribution"];
        }
        if ($returnAsLocale)
            return $result == null ? '' : R3NumberFormat($result, 2, true);
        return $result;
    }

    /**
     * Return estimated cost
     *
     * param array $data           the data array.
     *                             - wo_estimated_cost  Estimated cost
     *                             - wo_estimated_contribution  Estimated contribution
     * param bool $returnAsLocale  if true return the data as locale string
     * return mixed                the cost (as float or locale string)
     */
    static public function getNetEstimatedCost(array $data, $returnAsLocale = false) {
        return R3EcoGisCalculatorHelper::getNetCost($data, $returnAsLocale, 'estimated');
    }

    /**
     * Return estimated cost
     *
     * param array $data           the data array.
     *                             - wo_estimated_cost  Estimated cost
     *                             - wo_estimated_contribution  Estimated contribution
     * param bool $returnAsLocale  if true return the data as locale string
     * return mixed                the cost (as float or locale string)
     */
    static public function getNetEffectiveCost(array $data, $returnAsLocale = false) {
        return R3EcoGisCalculatorHelper::getNetCost($data, $returnAsLocale, 'effective');
    }

    /*
     * Return the founding return year (RI -> Tempo di ritorno investimento)
     *
     * param array $opt            Options. Valid options are:
     *                              - wo_effective_cost             Effective cost
     *                              - wo_estimated_cost             Estimated cost
     *                              - wo_primary_energy             Primary energy
     *                              - wo_primary_energy_price       Primary energy price
     *                              - wo_electricity                Electricity
     *                              - wo_electricity_price          Electricity price
     *                              - wo_year_mainten_cost          Maintainance costs
     *                              - wo_estimated_contribution     Estimated contribution
     *                              - wo_effective_contribution     Effective contribution
     */

    static public function getFundingReturnYear(array $data) {
        $result = null;
        $cost = $data['wo_effective_cost'] > 0 ? ($data['wo_effective_cost'] - $data['wo_effective_contribution']) : ($data['wo_estimated_cost'] - $data['wo_estimated_contribution']);
        $f = ($data['wo_primary_energy'] * $data['wo_primary_energy_price']) +
                ($data['wo_electricity'] * $data['wo_electricity_price']) +
                $data['wo_year_mainten_cost'];
        if ($cost > 0 && $f > 0)
            $result = ceil($cost / $f);
        return $result;
    }

    /*
     * Return the founding VAN (Valore attuale netto)
     *
     * param array $opt            Options. Valid options are:
     *                              - wo_effective_cost             Effective cost
     *                              - wo_estimated_cost             Estimated cost
     *                              - wo_primary_energy             Primary energy
     *                              - wo_primary_energy_price       Primary energy price
     *                              - wo_electricity                Electricity
     *                              - wo_electricity_price          Electricity price
     *                              - wo_year_mainten_cost          Maintainance costs
     *                              - wo_estimated_contribution     Estimated contribution
     *                              - wo_effective_contribution     Effective contribution
     *                              - wo_discount_rate              Discount rate
     *                              - wo_funding_lifetime           Vita utile investimento
     */

    static public function getVAN(array $data) {
        $van = null;
        if ($data['wo_discount_rate'] > 0 &&
                $data['wo_funding_lifetime'] > 0 &&
                ($data['wo_primary_energy'] * $data['wo_primary_energy_price']) + ($data['wo_electricity'] * $data['wo_electricity_price']) > 0) {
            $result = 0;
            $fundingLifetime = R3EcoGisCalculatorHelper::getFundingReturnYear($data);
            $discountRate = $data['wo_discount_rate'] / 100;
            $val1 = ($data['wo_primary_energy'] * $data['wo_primary_energy_price']) + ($data['wo_electricity'] * $data['wo_electricity_price']) - $data['wo_year_mainten_cost'];
            for ($year = 1; $year <= $data['wo_funding_lifetime']; $year++) {
                $val2 = pow(1 + $discountRate, $year);
                $result += $val1 / $val2;
            }
            $van = $result - ($data['wo_effective_cost'] > 0 ? $data['wo_effective_cost'] : $data['wo_estimated_cost']);
        }
        return $van;
    }

    /*
     * Return the energy converted data
     */

    static public function getConvertedData($do_id, $esu_id, $quantity) { //, $returnAsLocale = false) {
        static $data = null;

        if ($data === null) {
            $db = ezcDbInstance::get();
            $data = array();
            $sql = "SELECT * FROM ecogis.energy_source_udm where do_id={$do_id}";
            foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
                $data[$row['esu_id']] = $row;
            }
        }
        if (isset($data[$esu_id])) {
            $result = $data[$esu_id];
            if ($quantity !== '' && $quantity !== null) {
                $result['esu_tep_tot'] = round($quantity * $result['esu_tep_factor'], 3);
                $result['esu_co2_tot'] = round($quantity * $result['esu_co2_factor'], 2);
                $result['esu_kwh_tot'] = round($quantity * $result['esu_kwh_factor'], 0);
                $result['esu_energy_price_tot'] = $quantity * $result['esu_energy_price'];
            } else {
                $result = array_merge($result, array('esu_tep_tot' => null, 'esu_co2_tot' => null, 'esu_kwh_tot' => null, 'esu_energy_price_tot' => null));
            }
            return $result;
        }
        return null;
    }

    /*
     * Restituisce esu_id per la corrente
     *
     * @param boolean $primary   Se true restituisce l'id dell'energia elettrica primaria, altrimenti di quella secondaria
     */

    static public function getElectricityESU($primary) {
        static $data = array();

        if (!isset($data[$primary])) {
            $db = ezcDbInstance::get();
            $where = array('esu.do_id IS NULL');
            if ($primary === true) {
                $where[] = "et_code='HEATING' AND es_is_heating_electricity IS TRUE";
            } else {
                $where[] = "et_code='ELECTRICITY'";
            }
            $q = $db->createSelectQuery();
            $q->select("esu_id")
                    ->from('ecogis.energy_source_udm esu')
                    ->innerJoin('ecogis.energy_source es', 'es.es_id=esu.es_id')
                    ->innerJoin('ecogis.energy_type et', 'es.et_id=et.et_id')
                    ->where($where);
            $data[$primary] = $db->query($q)->fetchColumn();
        }
        return $data[$primary];
    }

}

class R3EcoGisCacheHelper {

    /**
     * Return the new document file id generated by a postgres sequence
     */
    public function getCacheId() {
        $db = ezcDbInstance::get();
        $sql = "SELECT NEXTVAL('cache_ca_id_seq')";
        return $db->query($sql)->fetchColumn(0);
    }

    /**
     * Return the cache file name
     */
    public function getCachedFileName($object, $object_id, $lang, $tollerance) {
        $path = R3_CACHE_DIR . strtolower($object) . '/' . sprintf(CACHE_MASK, $object_id / CACHE_DIR_LIMIT) . '/';
        $name = sprintf(CACHE_MASK, $object_id) . "_{$lang}_{$tollerance}" . CACHE_EXT;
        return "{$path}{$name}";
    }

    // Add a preview mapp to cache
    static public function addCacheElement($do_id, $object, $object_id, $fileName, $lang, $tollerance) {
        ezcLog::getInstance()->log(__METHOD__ . "({$do_id}, {$object}, {$object_id}, {$fileName}, {$lang}, {$tollerance}) called", ezcLog::DEBUG);

        $db = ezcDbInstance::get();

        $ca_id = R3EcoGisCacheHelper::getCacheId();
        ezcLog::getInstance()->log(__METHOD__ . ": CacheID: {$ca_id}", ezcLog::INFO);
        $cat_id = $db->query("SELECT cat_id FROM cache_type WHERE cat_code=" . $db->quote(strtoupper($object)))->fetchColumn();

        $sql = "INSERT INTO cache (ca_id, do_id, ca_object_id, cat_id, ca_lang, ca_tollerance)
                VALUES ($ca_id, $do_id, {$object_id}, $cat_id, {$lang}, " . $db->quote($tollerance) . ")";
        $db->exec($sql);
        $path = R3_CACHE_DIR . strtolower($object) . '/' . sprintf(CACHE_MASK, $object_id / CACHE_DIR_LIMIT) . '/';
        $name = sprintf(CACHE_MASK, $object_id) . "_{$lang}_{$tollerance}" . CACHE_EXT;
        if (!file_exists($path)) {
            ezcLog::getInstance()->log(__METHOD__ . ": Creating directory {$path}", ezcLog::INFO);
            mkdir($path);
        }
        if ($fileName <> '') {
            ezcLog::getInstance()->log(__METHOD__ . ": Coping {$fileName} => {$path}{$name}", ezcLog::INFO);
            copy($fileName, "{$path}{$name}");
            return $ca_id;
        }
        return false;
    }

    // Remove invalid data from cache
    static public function purgeMapPreviewCache($do_id, $obj_id = null, $bbox = null) {
        $db = ezcDbInstance::get();

        ezcLog::getInstance()->log(__METHOD__ . "({$do_id}, {$obj_id}, {$bbox}) called", ezcLog::DEBUG);
        $where = array('ca_expire_time<CURRENT_TIMESTAMP');
        if ($do_id > 0) {
            $where[] = 'do_id=' . (int) $do_id;
        }
        if ($obj_id > 0) {
            $where[] = 'ca_object_id=' . (int) $obj_id;
        }
        $q = $db->createSelectQuery();
        $q->select('ca_id, ca_lang, ca_tollerance, cat_code')
                ->from('cache ca')
                ->innerJoin('cache_type cat', 'ca.cat_id=cat.cat_id');
        if (count($where) > 0) {
            $q->where($where);
        }
        foreach ($db->query("{$q} FOR UPDATE") as $row) {
            $path = R3_CACHE_DIR . strtolower($row['cat_code']) . '/' . sprintf(CACHE_MASK, $row['ca_id'] / CACHE_DIR_LIMIT) . '/';
            $name = sprintf(CACHE_MASK, $row['ca_id']) . '_' . $row['ca_lang'] . '_' . $row['ca_tollerance'] . CACHE_EXT;
            $fileName = "{$path}{$name}";
            if (file_exists($fileName)) {
                ezcLog::getInstance()->log(__METHOD__ . ": Removing {$fileName}", ezcLog::INFO);
                unlink($fileName);
            }
            @rmdir($path);
            $db->exec("DELETE FROM cache WHERE ca_id={$row['ca_id']}");
            return true;
        }
        return false;
    }

    static function resetMapPreviewCache($do_id, $object_id = null) {
        ezcLog::getInstance()->log(__METHOD__ . "({$do_id}, {$object_id}) called", ezcLog::DEBUG);

        $do_id = (int) $do_id;
        $db = ezcDbInstance::get();
        $sql = "SELECT ca_id, ca_lang, ca_tollerance, cat_code
                FROM cache ca
                INNER JOIN cache_type cat ON ca.cat_id=cat.cat_id
                WHERE 1=1";
        if ($do_id > 0) {
            $sql .= "AND do_id={$do_id} ";
        }
        if ($object_id > 0) {
            $sql .= "AND ca_object_id={$object_id} ";
        }
        $sql .= "FOR UPDATE";
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $path = R3_CACHE_DIR . strtolower($row['cat_code']) . '/' . sprintf(CACHE_MASK, $row['ca_id'] / CACHE_DIR_LIMIT) . '/';
            $name = sprintf(CACHE_MASK, $row['ca_id']) . '_' . $row['ca_lang'] . '_' . $row['ca_tollerance'] . CACHE_EXT;
            $fileName = "{$path}{$name}";
            if (file_exists($fileName)) {
                ezcLog::getInstance()->log(__METHOD__ . ": Removing {$fileName}", ezcLog::INFO);
                unlink($fileName);
            }
            @rmdir($path);
            $db->exec("DELETE FROM cache WHERE ca_id={$row['ca_id']}");
        }
        foreach (glob(R3_CACHE_DIR . 'mappreview/*.cache') as $file) {
            unlink($file);
        }
        return true;
    }

    static function resetPhotoPreviewCache($do_id) {
        ezcLog::getInstance()->log(__METHOD__ . "({$do_id}) called", ezcLog::DEBUG);
        require_once R3_LIB_DIR . 'simplephoto.php';

        $do_id = (int) $do_id;
        $db = ezcDbInstance::get();

        $sql = "SELECT doc_id, doc_file, doc_file_id, doct_code
                FROM document doc
                INNER JOIN document_type doct ON doc.doct_id=doct.doct_id 
                WHERE doct_code IN ('BUILDING_PHOTO', 'BUILDING_LABEL', 'BUILDING_THERMOGRAPHY')
                FOR UPDATE";
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $ext = strrchr($row['doc_file'], '.');
            $path = R3_UPLOAD_DIR;
            $done = false;
            switch ($row['doct_code']) {
                case 'BUILDING_PHOTO':
                    $path = R3_UPLOAD_DIR . 'building/photo/' . sprintf(CACHE_MASK, $row['doc_file_id'] / CACHE_DIR_LIMIT) . '/' . PHOTO_THUMB_DIR . '/';
                    $done = true;
                    break;
                case 'BUILDING_LABEL':
                    $path = R3_UPLOAD_DIR . 'building/label/' . sprintf(CACHE_MASK, $row['doc_file_id'] / CACHE_DIR_LIMIT) . '/' . PHOTO_THUMB_DIR . '/';
                    $done = true;
                    break;
                case 'BUILDING_THERMOGRAPHY':
                    $path = R3_UPLOAD_DIR . 'building/thermography/' . sprintf(CACHE_MASK, $row['doc_file_id'] / CACHE_DIR_LIMIT) . '/' . PHOTO_THUMB_DIR . '/';
                    $done = true;
                    break;
            }
            if ($done) {
                $name = PHOTO_THUMB_PREFIX . sprintf(CACHE_MASK, $row['doc_file_id']) . $ext;
                $fileName = "{$path}{$name}";
                if (file_exists($fileName)) {
                    ezcLog::getInstance()->log(__METHOD__ . ": Removing {$fileName}", ezcLog::INFO);
                    unlink($fileName);
                }
                @rmdir($path);
            }
        }
        return true;
    }

    // Remove the files in the temporary directory
    static function removeTmpFiles($mask = array('*.tmp', 'fop-*.xml', 'fop-*.pdf', '*.xls')) {
        foreach ($mask as $m) {
            foreach (glob(R3_TMP_DIR . $m) as $file) {
                ezcLog::getInstance()->log(__METHOD__ . ": Removing {$file}", ezcLog::INFO);
                unlink($file);
            }
        }
        return true;
    }

    // Remove the web/output files
    static function removeMapOutputFiles($do_id, $removeLegend = false) {
        $auth = R3AuthInstance::get();
        if ($do_id === null) {
            $domains = array();
            foreach ($auth->getDomainsList() as $val)
                $domains[$val['dn_name']] = $val['dn_name'];
        } else {
            $domains[] = $auth->getDomainCodeFromID($do_id);
        }
        $mask = array('*.png', '*.gif', '*.jpg');
        foreach ($domains as $domain) {
            $path = R3_OUTPUT_DIR . strtolower($domain) . '/';
            foreach ($mask as $m) {
                foreach (glob("{$path}{$m}") as $file) {
                    unlink($file);
                }
                if ($removeLegend) {
                    foreach (glob("{$path}legend/{$m}") as $file) {
                        ezcLog::getInstance()->log(__METHOD__ . ": Removing {$file}", ezcLog::INFO);
                        unlink($file);
                    }
                    if (file_exists("{$path}legend/createimg.log")) {
                        ezcLog::getInstance()->log(__METHOD__ . ": Removing {$path}legend/createimg.log", ezcLog::INFO);
                        unlink("{$path}legend/createimg.log");
                    }
                }
            }
        }
        return true;
    }

}

// Return a formatted number
function R3NumberFormat($value, $decimals = null, $useThousandsSep = false, $maxDec = 10) {
    if (is_array($value)) {
        foreach ($value as $key => $val) {
            $value[$key] = R3NumberFormat($val, $decimals, $useThousandsSep, $maxDec);
        }
        return $value;
    } else {
        if (strlen($value) == '') {
            return '';
        }
        if (!defined("__R3_LOCALE__")) {
            require_once 'r3locale.php';
        }
        $oldLocale = setlocale(LC_NUMERIC, 0);
        setlocale(LC_NUMERIC, getLocaleInfo(R3Locale::getLanguageCode()));
        setLangInfo(array('thousands_sep' => "."));
        $localeInfo = getLocaleInfo(getLangLocaleByCode(R3Locale::getLanguageCode()));
        if ($decimals === null) {
            $diff = round($value - (int) $value, $maxDec);
            if ($diff == 0) {
                $decimals = 0;
            } else {
                $decimals = strlen($diff) - 2;  // -2 is 0. of the number
            }
        }
        $thousands_sep = $useThousandsSep === true ? $localeInfo['thousands_sep'] : '';
        $result = number_format($value, $decimals, $localeInfo['decimal_point'], $thousands_sep);
        setlocale(LC_ALL, $oldLocale);
        return $result;
    }
}

class R3EcoGisEventNotifier {

    function invalidateSimulations() {
        require_once R3_LIB_DIR . 'eco_simulator.php';
        R3Simulator::invalidateAllSimulations($_SESSION['do_id']);
    }

    static function notifyDataChanged(R3AppBaseObject $class, array $opt = array()) {
        self::invalidateSimulations();
    }

}

function R3DateFormat($date, $fmt) {
    $res = '';

    $res = trim($date);
    if (strlen($res) > 0) {
        $res = substr($res, 0, 10);
        $resArr = explode('-', $res);
        $res = $resArr[2] . '/' . $resArr[1] . '/' . $resArr[0];
    }

    return($res);
}

/**
 * Replace the null value with an emptyt string (IE problems)
 *
 * @param mixed $data               the data to adjust
 * return mixed                     the adjusted data
 */
function Null2Str($data) {
    if (is_array($data)) {
        foreach ($data as $key => $val)
            $data[$key] = Null2Str($val);
    } else {
        if ($data === null)
            return '';
    }
    return $data;
}

// Explode a size array (EG: 500x300. Return width: 500, height: 300)
function explodeSize($value, $default = null) {
    $a = explode('x', strtolower($value));
    if (count($a) <= 2) {
        return array('width' => $a[0], 'height' => $a[1]);
    }
    return array('width' => 100, 'height' => 100);
}

/**
 * Fetch coordinate from postgis ST_Extent result, which is a BOX
 *
 * @param string $the_geom
 * @return array
 */
function ST_FetchBox($the_geom) {
    if (preg_match("/BOX\((\d*.\d*) (\d*.\d*),(\d*.\d*) (\d*.\d*)\)/", $the_geom, $matches)) {
        $ret = array();
        $ret[] = $matches[1]; // x1
        $ret[] = $matches[2]; // y1
        $ret[] = $matches[3]; // x2
        $ret[] = $matches[4]; // y2
    } else {
        throw new Exception("no extent available");
    }
    return $ret;
}

/**
 * explode a comma-delimited string to an array
 */
function explodeInt($array, $delimiter = ',', $ignoreEmpty = true) {
    $result = array();
    foreach (explode($delimiter, $array) as $val) {
        $val = trim($val);
        if (($val <> '' || !$ignoreEmpty) && (int) $val == $val) {
            $result[] = (int) $val;
        }
    }
    return $result;
}

/**
 * Return an UUID (v4)
 * @return string
 * @see http://php.net/manual/en/function.uniqid.php
 */
function uuid($isSystem = false) {
    if ($isSystem) {
        $mask = 0x8000;
    } else {
        $mask = 0;
    }
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0x0fff) | $mask, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
}

/**
 * Remove the old file in the temporary directory
 * param integer $tto       the max number of seconds of the file (0 not allowed. Use -1 instead)
 * param array $opt         options. Valid options are:
 *                          - ext array:  the list of extensions to delete (prevent accidental deletion)
 *                                        extension is case-insentitive
 * return false on error or the number of deleted files
 */
function cleanTmporaryDirs($ttl, $opt) {
    if ($ttl == 0)
        return false;
    if (!isset($opt['ext']))
        return false;
    if (!is_array($opt['ext']))
        $opt['ext'] = array($opt['ext']);
    $tot = 0;
    foreach (glob(R3_TMP_DIR . '*') as $file) {
        $isOld = (time() - filectime($file)) > $ttl;
        $ext = strtolower(strrchr($file, '.'));
        if (is_file($file) && $isOld && in_array($ext, $opt['ext'])) {
            unlink($file);
        }
    }
}
