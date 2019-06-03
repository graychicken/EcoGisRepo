<?php

/**
 * Local management
 */
class R3Security {

    /**
     * Return true if the default check pass
     * param integer $id            id to check
     * @return boolean              Return true on success. Exception on fai
     */
    static public function checkDefault($id, $kind = '') {
        $auth = R3AuthInstance::get();
        $db = ezcDbInstance::get();
        if ($auth === null)
            throw new Exception("Authentication required");
        if ($db === null)
            throw new Exception("Database not initialized");
        if ($id == '')
            throw new Exception("Missing {$kind} ID");
        if ((int) $id == 0)
            throw new Exception("Invalid {$kind} ID");
        return true;
    }

    /**
     * Return true if the given municipality ID (mu_id) can handled by current user
     * param integer $bu_id         Building id
     * @return boolean              Return true on success. Exception on fai
     */
    static public function checkMunicipality($mu_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($mu_id, 'municipality');
        if (!isset($cache[$mu_id])) {
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = $q->expr->eq('do_id', $_SESSION['do_id']);
            $where[] = $q->expr->eq('mu_id', $mu_id);
            if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getParam('mu_id') <> '') {
                $where[] = $q->expr->eq('mu_id', $db->quote((int) $auth->getParam('mu_id')));
            }
            $q->select('COUNT(*)')
                    ->from('municipality')
                    ->where($where);
            // echo $q;
            if ($db->query($q)->fetchColumn() <> 1) {
                throw new Exception("Municipality #{$mu_id} not found");
            }
            $cache[$mu_id] = true;
        }
        return true;
    }

    /**
     * Return true if the given municipality ID (mu_id) can handled by current user
     * param integer $bu_id         Building id
     * @return boolean              Return true on success. Exception on fai
     */
    static public function checkDocument($doc_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($doc_id, 'document');
        if (!isset($cache[$doc_id])) {
            $doct_code = $db->query("SELECT doct_code FROM document_data WHERE doc_id=" . (int) $doc_id)->fetchColumn();
            switch ($doct_code) {
                case 'BUILDING':
                    $join = array('building_data' => 'doc_object_id=bu_id');
                    break;
                case 'STREET_LIGHTING':
                    $join = array('street_lighting_data' => 'doc_object_id=sl_id');
                    break;
                case 'GLOBAL_ENTRY':
                    $join = array('global_entry_data' => 'doc_object_id=ge_id');
                    break;
                case 'GLOBAL_PLAIN':
                    $join = array('global_plain_data' => 'doc_object_id=gp_id');
                    break;


                default:
                    if ($doct_code === false) {
                        throw new Exception("Document #{$doc_id} not found");
                    }
                    throw new Exception("Unknown document type \"{$doct_code}\"");
            }

            $q = $db->createSelectQuery();
            $where = array();
            $where[] = $q->expr->eq('do_id', $_SESSION['do_id']);
            $where[] = $q->expr->eq('doc_id', $doc_id);
            if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getParam('mu_id') <> '') {
                $where[] = $q->expr->eq('mu_id', $db->quote((int) $auth->getParam('mu_id')));
            }
            $q->select('COUNT(*)')
                    ->from('document doc')
                    ->innerJoin(key($join), current($join))
                    ->where($where);
            if ($db->query($q)->fetchColumn() <> 1) {
                throw new Exception("Document #{$doc_id} not found");
            }
            $cache[$doc_id] = true;
        }
        return true;
    }

    static public function checkDocumentByFileId($doc_file_id) {
        $db = ezcDbInstance::get();
        $sql = "SELECT doc_id FROM document WHERE doc_file_id=" . (int) $doc_file_id;
        $doc_id = $db->query($sql)->fetchColumn();
        R3Security::checkDefault($doc_id, 'document [file]');
        return self::checkDocument($doc_id);
    }

    /**
     * Return true if the given building ID (bu_id) can handled by current user
     * param integer $bu_id         Building id
     * @return boolean              Return true on success. Exception on fai
     */
    static public function checkBuilding($bu_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($bu_id, 'building');
        if (!isset($cache[$bu_id])) {
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = $q->expr->eq('do_id', $_SESSION['do_id']);
            $where[] = $q->expr->eq('bu_id', $bu_id);
            if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getParam('mu_id') <> '') {
                $where[] = $q->expr->eq('mu_id', $db->quote((int) $auth->getParam('mu_id')));
            }
            $q->select('COUNT(*)')
                    ->from('building_data')
                    ->where($where);
            // echo $q;
            if ($db->query($q)->fetchColumn() <> 1) {
                throw new Exception("Building #{$bu_id} not found");
            }
            $cache[$bu_id] = true;
        }
        return true;
    }

    /**
     * Return true if the given building ID (bu_id) can handled by current user
     * param integer $bu_id         Building id
     * @return boolean              Return true on success. Exception on fai
     */
    static public function checkStreetlighting($sl_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($sl_id, 'street_lighting');
        if (!isset($cache[$sl_id])) {
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = $q->expr->eq('do_id', $_SESSION['do_id']);
            $where[] = $q->expr->eq('sl_id', $sl_id);
            if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getParam('mu_id') <> '') {
                $where[] = $q->expr->eq('mu_id', $db->quote((int) $auth->getParam('mu_id')));
            }
            $q->select('COUNT(*)')
                    ->from('street_lighting_data')
                    ->where($where);
            // echo $q;
            if ($db->query($q)->fetchColumn() <> 1) {
                throw new Exception("Street lighting #{$sl_id} not found");
            }
            $cache[$sl_id] = true;
        }
        return true;
    }

    /**
     * Return true if the given building ID (bu_id) can handled by current user
     * param integer $bu_id         Building id
     * @return boolean              Return true on success. Exception on fai
     */
    static public function checkGlobalSubcategory($gs_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($gs_id, 'global_subcategory_data');
        if (!isset($cache[$gs_id])) {
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = $q->expr->eq('do_id', $_SESSION['do_id']);
            $where[] = $q->expr->eq('gs_id', $gs_id);
            if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getParam('mu_id') <> '') {
                $where[] = $q->expr->eq('mu_id', $db->quote((int) $auth->getParam('mu_id')));
            }
            $q->select('COUNT(*)')
                    ->from('global_subcategory_data')
                    ->where($where);
            // echo $q;
            if ($db->query($q)->fetchColumn() <> 1) {
                throw new Exception("global sub-categort #{$gs_id} not found");
            }
            $cache[$gs_id] = true;
        }
        return true;
    }

    /**
     * Return true if the given building ID (bu_id) can handled by current user
     * param integer $bu_id         Building id
     * @return boolean              Return true on success. Exception on fai
     */
    static public function checkGlobalEntry($ge_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($ge_id, 'global_entry');
        if (!isset($cache[$ge_id])) {
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = $q->expr->eq('do_id', $_SESSION['do_id']);
            $where[] = $q->expr->eq('ge_id', $ge_id);
            if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getParam('mu_id') <> '') {
                $where[] = $q->expr->eq('mu_id', $db->quote((int) $auth->getParam('mu_id')));
            }
            $q->select('COUNT(*)')
                    ->from('global_entry_data')
                    ->where($where);
            // echo $q;
            if ($db->query($q)->fetchColumn() <> 1) {
                throw new Exception("global entry #{$ge_id} not found");
            }
            $cache[$ge_id] = true;
        }
        return true;
    }

    /**
     * Return true if the given building ID (bu_id) can handled by current user
     * param integer $bu_id         Building id
     * @return boolean              Return true on success. Exception on fai
     */
    static public function checkEnergyMeter($em_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($em_id, 'energy_meter');
        if (!isset($cache[$em_id])) {
            $where = 'em_id=' . (int) $em_id;
            $q = $db->createSelectQuery();
            $q->select('emo_code, em_object_id')
                    ->from('energy_meter_data')
                    ->where($where);
            $data = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            if ($data === false) {
                throw new Exception("Energy meter #{$em_id} not found");
            }
            switch ($data['emo_code']) {
                case 'BUILDING':
                    self::checkBuilding($data['em_object_id']);
                    break;
                default:
                    throw new Exception("Energy meter #{$em_id} is of unknown type (\"{$data['emo_code']}\")");
            }
            $cache[$em_id] = true;
        }
        return true;
    }

    // Check security for a building energy meter 8based on act and method)
    static public function checkEnergyMeterForBuilding($act, $bu_id, $em_id, array $opt = array()) {
        $opt = array_merge(array('method' => '', 'skip_methods' => array(), 'kind' => null), $opt);

        if (!in_array($opt['method'], $opt['skip_methods'])) {
            if ($act == 'add') {
                R3Security::checkBuilding($bu_id);
            } else {
                // Can edit/delete the given id
                R3Security::checkEnergyMeter($em_id);
            }
        }
    }

    /**
     * Return true if the given device ID (dev_id) can handled by current user
     * param integer $dev_id        Device id
     * @return boolean              Return true on success. Exception on fai
     */
    static public function checkDevice($dev_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($dev_id, 'device');
        if (!isset($cache[$dev_id])) {
            $where = 'dev_id=' . (int) $dev_id;
            $q = $db->createSelectQuery();
            $q->select('em_id')
                    ->from('device_data')
                    ->where($where);
            $data = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            if ($data === false) {
                throw new Exception("Device #{$dev_id} not found");
            }
            self::checkEnergyMeter($data['em_id']);
            $cache[$dev_id] = true;
        }
        return true;
    }

    // Check security for a building energy meter 8based on act and method)
    static public function checkDeviceForEnergyMeter($act, $em_id, $dev_id, array $opt = array()) {
        $opt = array_merge(array('method' => '', 'skip_methods' => array(), 'kind' => null), $opt);

        if (!in_array($opt['method'], $opt['skip_methods'])) {
            if ($act == 'add') {
                R3Security::checkEnergyMeter($em_id);
            } else {
                // Can edit/delete the given id
                R3Security::checkDevice($dev_id);
            }
        }
    }

    /**
     * Return true if the given building ID (bu_id) can handled by current user
     * param integer $bu_id         Building id
     * @return boolean              Return true on success. Exception on fai
     */
    static public function checkConsumption($co_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($co_id, 'consumption');
        if (!isset($cache[$co_id])) {
            $where = 'co_id=' . (int) $co_id;
            $q = $db->createSelectQuery();
            $q->select('emo_code, em_object_id')
                    ->from('consumption_data')
                    ->where($where);
            $data = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            if ($data === false) {
                throw new Exception("Consumption meter #{$co_id} not found");
            }
            switch ($data['emo_code']) {
                case 'BUILDING':
                    self::checkBuilding($data['em_object_id']);
                    break;
                case 'STREET_LIGHTING':
                    self::checkStreetlighting($data['em_object_id']);
                    break;
                default:
                    throw new Exception("Energy meter #{$co_id} is of unknown type (\"{$data['emo_code']}\")");
            }
            $cache[$co_id] = true;
        }
        return true;
    }

    // Check security for a building energy meter 8based on act and method)
    static public function checkConsumptionForEnergyMeter($act, $em_id_or_sl_id, $co_id, array $opt = array()) {
        $opt = array_merge(array('method' => '', 'skip_methods' => array(), 'kind' => null), $opt);

        if (!in_array($opt['method'], $opt['skip_methods'])) {
            if ($act == 'add') {
                if ($opt['kind'] == 'street_lighting') {
                    R3Security::checkStreetLighting($em_id_or_sl_id);
                } else {
                    R3Security::checkEnergyMeter($em_id_or_sl_id);
                }
            } else {
                // Can edit/delete the given id
                R3Security::checkConsumption($co_id);
            }
        }
    }

    // Check security for a building energy meter 8based on act and method)
    static public function checkActionCatalogForBuilding($act, $bu_id, $ac_id, array $opt = array()) {
        $opt = array_merge(array('method' => '', 'skip_methods' => array(), 'kind' => null), $opt);

        if (!in_array($opt['method'], $opt['skip_methods'])) {
            if ($act == 'add' || ($act == 'list' && $bu_id <> '')) {
                R3Security::checkBuilding($bu_id);
            } else {
                // Can edit/delete the given id
                R3Security::checkActionCatalog($ac_id);
            }
        }
    }

    static public function checkGlobalStrategy($gst_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($gst_id, 'global_strategy');
        if (!isset($cache[$gst_id])) {
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = $q->expr->eq('do_id', $_SESSION['do_id']);
            $where[] = $q->expr->eq('gst_id', $gst_id);
            if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getParam('mu_id') <> '') {
                $where[] = $q->expr->eq('mu_id', $db->quote((int) $auth->getParam('mu_id')));
            }
            $q->select('COUNT(*)')
                    ->from('global_strategy_data')
                    ->where($where);
            if ($db->query($q)->fetchColumn() <> 1) {
                throw new Exception("Global strategy #{$gst_id} not found");
            }
            $cache[$gst_id] = true;
        }
        return true;
    }

    static public function checkGlobalResult($ge_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($ge_id, 'global_entry');
        if (!isset($cache[$ge_id])) {
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = $q->expr->eq('do_id', $_SESSION['do_id']);
            $where[] = $q->expr->eq('ge_id', $ge_id);
            if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getParam('mu_id') <> '') {
                $where[] = $q->expr->eq('mu_id', $db->quote((int) $auth->getParam('mu_id')));
            }
            $q->select('COUNT(*)')
                    ->from('global_entry_data')
                    ->where($where);
            if ($db->query($q)->fetchColumn() <> 1) {
                throw new Exception("Global result #{$ge_id} not found");
            }
            $cache[$ge_id] = true;
        }
        return true;
    }

    static public function checkGlobalPlain($gp_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($gp_id, 'global_plain');
        if (!isset($cache[$gp_id])) {
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = $q->expr->eq('do_id', $_SESSION['do_id']);
            $where[] = $q->expr->eq('gp_id', $gp_id);
            if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getParam('mu_id') <> '') {
                $where[] = $q->expr->eq('mu_id', $db->quote((int) $auth->getParam('mu_id')));
            }
            $q->select('COUNT(*)')
                    ->from('global_plain_data')
                    ->where($where);
            if ($db->query($q)->fetchColumn() <> 1) {
                throw new Exception("Global plain #{$gp_id} not found");
            }
            $cache[$gp_id] = true;
        }
        return true;
    }

    static public function checkGlobalPlainRow($gpr_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($gpr_id, 'global_plain_row');
        if (!isset($cache[$gpr_id])) {
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = $q->expr->eq('do_id', $_SESSION['do_id']);
            $where[] = $q->expr->eq('gpr_id', $gpr_id);
            if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getParam('mu_id') <> '') {
                $where[] = $q->expr->eq('mu_id', $db->quote((int) $auth->getParam('mu_id')));
            }
            $q->select('COUNT(*)')
                    ->from('global_plain_data gp')
                    ->innerJoin('global_plain_row gpr', 'gp.gp_id=gpr.gp_id')
                    ->where($where);
            if ($db->query($q)->fetchColumn() <> 1) {
                throw new Exception("Global plain row #{$gpr_id} not found");
            }
            $cache[$gpr_id] = true;
        }
        return true;
    }

    static public function checkActionCatalog($ac_id) {
        static $cache = array();

        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        R3Security::checkDefault($ac_id, 'action_catalog');
        if (!isset($cache[$ac_id])) {
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = $q->expr->eq('do_id', $_SESSION['do_id']);
            $where[] = $q->expr->eq('ac_id', $ac_id);
            if (!$auth->hasPerm('SHOW', 'ALL_DOMAINS') && $auth->getParam('mu_id') <> '') {
                $where[] = $q->expr->eq('mu_id', $db->quote((int) $auth->getParam('mu_id')));
            }
            $q->select('COUNT(*)')
                    ->from('action_catalog_data')
                    ->where($where);
            // echo $q;
            if ($db->query($q)->fetchColumn() <> 1) {
                throw new Exception("Action catalog #{$ac_id} not found");
            }
            $cache[$ac_id] = true;
        }
        return true;
    }

    // Check security for a building energy meter 8based on act and method)
    static public function checkDocumentForObject($act, $object_id, $doc_id, array $opt = array()) {
        $opt = array_merge(array('method' => '', 'skip_methods' => array(), 'kind' => null), $opt);

        if (!in_array($opt['method'], $opt['skip_methods'])) {
            if ($act == 'add' || ($act == 'list' && $object_id <> '')) {
                switch ($opt['kind']) {
                    case 'building':
                        R3Security::checkBuilding($object_id);
                        break;
                    case 'street_lighting':
                        R3Security::checkStreetlighting($object_id);
                        break;
                    case 'global_entry':
                        R3Security::checkGlobalEntry($object_id);
                        break;
                    case 'global_plain':
                        R3Security::checkGlobalPlain($object_id);
                        break;

                    default:
                        throw new Exception("Invalid kind \"{$opt['kind']}\" for document#{$doc_id}");
                }
            } else {
                R3Security::checkDocument($doc_id);
            }
        }
    }

}
