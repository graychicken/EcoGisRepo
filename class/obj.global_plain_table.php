<?php

require_once R3_CLASS_DIR . 'obj.global_plain_row.php';

class R3EcoGisGlobalPlainTableHelper {

    static private function concatNames($name1, $name2) {
        if ($name1 == $name2) {
            return $name1;
        }
        if ($name1 == '') {
            return $name2;
        }
        if (in_array($name2, array('', '-'))) {
            return $name1;
        }
        return "{$name1} - {$name2}";
    }

    /**
     *
     * @param <type> $ge_id
     * @param <type> $kind
     * @param <type> $divider Return the table data
     */
    static private function getDataMunicipality($do_id, $gp_id) {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        $calculateCategorySum = $auth->getConfigValue('APPLICATION', 'CALCULATE_GLOBAL_PLAIN_TOTALS', 'T') == 'T';

        $other = _('Altro');
        $sql = "SELECT gc1.gc_id,gc1.gc_parent_id,gc1.do_id,
                       gc2.gc_code AS gc_code_main,gc2.gc_name_{$lang} AS gc_name_main,gc2.gc_order AS gc_order_main,
                       gc1.gc_code, gc1.gc_name_{$lang} AS gc_name, gc1.gc_order,
                       gps_id,gps.gp_id AS pg_id_sum,gps_expected_energy_saving,gps_expected_renewable_energy_production,gps_expected_co2_reduction,
                       CASE gc1.gc_has_extradata WHEN TRUE THEN 'T' WHEN FALSE THEN 'F' END AS gc_has_extradata, gpr_id,
                       CASE WHEN gc_extradata_{$lang} IS NULL THEN gc1.gc_name_{$lang} ELSE gc1.gc_name_$lang || ' - ' || gc_extradata_$lang END AS gc_fullname,
                       gpr.gpa_id, gpa.gpa_code, 
                       
                       CASE WHEN gpr.gpa_extradata_{$lang} IS NULL THEN gpa_name_{$lang}
                       ELSE CASE WHEN UPPER(gpa_name_{$lang})=UPPER('{$other}') THEN gpr.gpa_extradata_{$lang}
                           ELSE gpa_name_{$lang} || ' - ' || gpr.gpa_extradata_{$lang}
                           END 
                       END AS gpa_name,
                       gpr_descr_$lang AS gpr_descr, gpr_responsible_department_$lang AS gpr_responsible_department,
                       gpr_start_date, gpr_end_date, gpr_estimated_cost, gpr_expected_energy_saving, gpr_expected_renewable_energy_production,
                       gpr_expected_co2_reduction, gpr_imported_row, gpr_gauge_type
                FROM ecogis.global_category gc1
                INNER JOIN ecogis.global_category gc2 ON gc1.gc_parent_id=gc2.gc_id
                LEFT JOIN ecogis.global_plain_row gpr ON gc1.gc_id=gpr.gc_id AND gpr.gp_id={$gp_id}
                LEFT JOIN ecogis.global_plain_action gpa ON gpa.gpa_id=gpr.gpa_id
                LEFT JOIN ecogis.global_plain_sum gps ON gc2.gc_id=gps.gc_id and gps.gp_id={$gp_id}
                WHERE (gc1.do_id IS NULL OR gc1.do_id={$do_id}) AND gc1.gc_global_plain IS TRUE AND gc2.gc_global_plain IS TRUE AND
                      gc1.gc_visible IS TRUE and gc2.gc_visible IS TRUE
                ORDER BY gc_order_main, gc_name_main, gc_order, gc_name, gc_fullname, gpr_order, gpa_name, gpr_id";
        $result = array();
        $sumData = array();  // Serve per somme di categoria e totali
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $progress = R3EcoGisGlobalPlainHelper::getActionStatus($row['gpr_id']);

            // Dati per somma totali categoria (e totalone)
            if (!isset($sumData[$row['gc_parent_id']])) {
                $sumData[$row['gc_parent_id']] = array('energy_tot' => null, 'emission_tot' => null);
            }
            if ($progress['energy_tot'] != '') {
                $sumData[$row['gc_parent_id']]['energy_tot'] += $progress['energy_tot'];
            }
            if ($progress['emission_tot'] != '') {
                $sumData[$row['gc_parent_id']]['emission_tot'] += $progress['emission_tot'];
            }

            if (!isset($result['data'][$row['gc_parent_id']]['rowspan'])) {
                $result['data'][$row['gc_parent_id']]['rowspan'] = 0;
            }
            if (!isset($result['data'][$row['gc_parent_id']]['sum'])) {
                $result['data'][$row['gc_parent_id']]['sum'] = array(
                    'expected_energy_saving' => null,
                    'expected_renewable_energy_production' => null,
                    'expected_co2_reduction' => null,
                    'progress_energy' => null,
                    'progress_emission' => null);
            }
            $result['data'][$row['gc_parent_id']]['rowspan'] ++;
            $result['data'][$row['gc_parent_id']]['gc_id'] = $row['gc_id'];
            $result['data'][$row['gc_parent_id']]['gc_parent_id'] = $row['gc_parent_id'];
            $result['data'][$row['gc_parent_id']]['code'] = $row['gc_code_main'];
            $result['data'][$row['gc_parent_id']]['name'] = $row['gc_name_main'];
            if ($calculateCategorySum) {
                // Sum data (ignoring database entry) - New configuration
                if ($row['gpr_expected_energy_saving'] !== null) {
                    $result['data'][$row['gc_parent_id']]['sum']['expected_energy_saving'] += $row['gpr_expected_energy_saving'];
                }
                if ($row['gpr_expected_renewable_energy_production'] !== null) {
                    $result['data'][$row['gc_parent_id']]['sum']['expected_renewable_energy_production'] += $row['gpr_expected_renewable_energy_production'];
                }
                if ($row['gpr_expected_co2_reduction'] !== null) {
                    $result['data'][$row['gc_parent_id']]['sum']['expected_co2_reduction'] += $row['gpr_expected_co2_reduction'];
                }
            } else {
                // Use database entry for sum category data (table ecogis.global_plain_sum) - Old configuration. SHould be switched to automatic calculation
                $result['data'][$row['gc_parent_id']]['sum']['expected_energy_saving'] = $row['gps_expected_energy_saving'];
                $result['data'][$row['gc_parent_id']]['sum']['expected_renewable_energy_production'] = $row['gps_expected_renewable_energy_production'];
                $result['data'][$row['gc_parent_id']]['sum']['expected_co2_reduction'] = $row['gps_expected_co2_reduction'];
            }
            $result['data'][$row['gc_parent_id']]['categories'][$row['gc_id']]['name'] = $row['gc_name'];
            $result['data'][$row['gc_parent_id']]['categories'][$row['gc_id']]['code'] = $row['gc_code'];
            $result['data'][$row['gc_parent_id']]['categories'][$row['gc_id']]['has_extradata'] = $row['gc_has_extradata'];
            $result['data'][$row['gc_parent_id']]['categories'][$row['gc_id']]['data'][$row['gpr_id']] = array('code' => $row['gpa_code'],
                'imported_row' => $row['gpr_imported_row'],
                'gauge_type' => $row['gpr_gauge_type'],
                'progress_energy' => round($progress['energy_perc'], 1),
                'progress_emission' => round($progress['emission_perc'], 1), //$row['gpr_expected_co2_reduction'], // <> 0 ? round($progress['perc_tot']/$row['gps_expected_co2_reduction']) : null,
                'name' => $row['gpa_name'],
                'fullname' => $row['gc_fullname'],
                'descr' => $row['gpr_descr'],
                'responsible_department' => $row['gpr_responsible_department'],
                'start_date' => $row['gpr_start_date'],
                'end_date' => $row['gpr_end_date'],
                'estimated_cost' => $row['gpr_estimated_cost'],
                'expected_energy_saving' => $row['gpr_expected_energy_saving'],
                'expected_renewable_energy_production' => $row['gpr_expected_renewable_energy_production'],
                'expected_co2_reduction' => $row['gpr_expected_co2_reduction']);
        }
        $sum = array(
            'expected_energy_saving' => null,
            'expected_renewable_energy_production' => null,
            'expected_co2_reduction' => null,
            'progress_energy' => null,
            'progress_emission' => null
        );
        $energyTot = null;
        $emisisonTot = null;
        foreach ($result['data'] as $row) {

            if ($row['sum']['expected_energy_saving'] <> '') {
                $sum['expected_energy_saving'] += $row['sum']['expected_energy_saving'];
            }
            if ($row['sum']['expected_renewable_energy_production'] <> '') {
                $sum['expected_renewable_energy_production'] += $row['sum']['expected_renewable_energy_production'];
            }
            if ($row['sum']['expected_co2_reduction'] <> '') {
                $sum['expected_co2_reduction'] += $row['sum']['expected_co2_reduction'];
            }

            // Imposto Percentuali completamento
            if ($row['sum']['expected_energy_saving'] + $row['sum']['expected_renewable_energy_production'] <> 0) {
                $energyTot += $sumData[$row['gc_parent_id']]['energy_tot'];
                $result['data'][$row['gc_parent_id']]['sum']['progress_energy'] = round($sumData[$row['gc_parent_id']]['energy_tot'] /
                        ($row['sum']['expected_energy_saving'] + $row['sum']['expected_renewable_energy_production']) * 100, 1);
            }
            if ($row['sum']['expected_co2_reduction'] <> 0) {
                $emisisonTot += $sumData[$row['gc_parent_id']]['emission_tot'];
                $result['data'][$row['gc_parent_id']]['sum']['progress_emission'] = round($sumData[$row['gc_parent_id']]['emission_tot'] / $row['sum']['expected_co2_reduction'] * 100, 1);
            }
        }
        if (($sum['expected_energy_saving'] + $sum['expected_renewable_energy_production']) <> 0) {
            $sum['progress_energy'] = round($energyTot / ($sum['expected_energy_saving'] + $sum['expected_renewable_energy_production']) * 100, 1);
        }
        if ($sum['expected_co2_reduction'] <> 0) {
            $sum['progress_emission'] = round($emisisonTot / $sum['expected_co2_reduction'] * 100, 1);
        }
        $result['sum'] = $sum;

        return $result;
    }

    static public function getData($do_id, $gp_id, $mergeMunicipalityCollection = true) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $sql = "SELECT mu_id, mu_type FROM ecogis.global_plain_data WHERE gp_id=" . (int) $gp_id;
        $muData = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        if ($muData === false) {
            throw new Exception("Global entry #{$ge_id} not found");
        }
        if ($muData['mu_type'] == 'C') {
            // Municipality collection
            // Get collection data and other municipality data

            $result = self::getDataMunicipality($do_id, $gp_id);
            //$result = array();
            if ($mergeMunicipalityCollection) {
                // Get all the other municipality inventory. Multiple inventory/year can be present. 
                $sql = "SELECT gp_id, mu_name_{$lang} as mu_name
                    FROM ecogis.municipality mu
                    INNER JOIN ecogis.global_plain gp ON mu.mu_id=gp.mu_id AND gp_exclude_from_collection IS FALSE
                    WHERE mu_parent_id={$muData['mu_id']}    
                    ORDER BY mu_name_{$lang}";
                foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
                    $data = self::getDataMunicipality($do_id, $row['gp_id']);
                    self::meregeData($result, $data, $row['mu_name']);
                }
            }
            return $result;
        } else {
            // Single municipality
            return self::getDataMunicipality($do_id, $gp_id);
        }
    }

    static private function meregeData(&$result, &$data, $text) {
        foreach ($data['data'] as $gc_parent_id => $dummy1) {
            foreach ($data['data'][$gc_parent_id]['categories'] as $gc_id => $dummy2) {
                foreach ($data['data'][$gc_parent_id]['categories'][$gc_id]['data'] as $gpr_id => $dummy3) {
                    if (!empty($gpr_id)) {
                        // elimina righe vuote (che servono per il rendering senza dati)
                        $k = key($result['data'][$gc_parent_id]['categories'][$gc_id]['data']);
                        if (empty($k)) {
                            unset($result['data'][$gc_parent_id]['categories'][$gc_id]['data'][$k]);
                        }

                        // Merge
                        $result['data'][$gc_parent_id]['categories'][$gc_id]['data'][$gpr_id] = $data['data'][$gc_parent_id]['categories'][$gc_id]['data'][$gpr_id];
                        // Aggiungo nome comune
                        $result['data'][$gc_parent_id]['categories'][$gc_id]['data'][$gpr_id]['name'] = self::concatNames($text, $result['data'][$gc_parent_id]['categories'][$gc_id]['data'][$gpr_id]['name']);
                        // Evito modifica e cancellazione
                        $result['data'][$gc_parent_id]['categories'][$gc_id]['data'][$gpr_id]['can_mod'] = false;
                        $result['data'][$gc_parent_id]['categories'][$gc_id]['data'][$gpr_id]['can_del'] = false;
                    }
                }
            }

            // Somma macrocategoria
            foreach ($data['data'][$gc_parent_id]['sum'] as $key => $dummy) {
                if (!empty($data['data'][$gc_parent_id]['sum'][$key])) {
                    $result['data'][$gc_parent_id]['sum'][$key] += $data['data'][$gc_parent_id]['sum'][$key];
                }
            }
        }
        // Table sum
        foreach ($data['sum'] as $key => $dummy) {
            $result['sum'][$key] += $data['sum'][$key];
        }
    }

    static public function canMonitoring($do_id, $gp_id) {
        $gp_id = (int) $gp_id;
        $db = ezcDbInstance::get();
        $sql = "SELECT COUNT(*) FROM ecogis.global_strategy WHERE gp_id={$gp_id}";
        return $db->query($sql)->fetchColumn();
    }

}

class eco_global_plain_table extends R3AppBaseObject {

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->id = initVar('id');
        $this->gp_id = (int) initVar('gp_id');
        $this->act = initVar('act', initVar('parent_act', 'mod'));
        $this->parent_act = initVar('parent_act', 'show');
        $this->kind = strToUpper(initVar('kind'));
        $this->do_id = $_SESSION['do_id'];
        $this->merge_municipality_data = PageVar('merge_municipality_data', 'T') == 'T' ? true : false;
    }

    public function getPageTitle() {
        
    }

    public function getFilterValues() {
        
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {
        
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {
        
    }

    public function getListTableRowOperations(&$row) {
        
    }

    /**
     * Return the data for a single customer 
     */
    public function getData($id = null) {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        $vlu = array();
        if ($this->act <> 'add') {
            $sql = "SELECT mu_type FROM ecogis.global_plain_data WHERE gp_id=" . (int) $this->gp_id;
            $vlu['mu_type'] = $db->query($sql)->fetchColumn();
            $vlu['merge_municipality_data'] = $this->merge_municipality_data;
            $vlu['data'] = R3EcoGisGlobalPlainTableHelper::getData($this->do_id, $this->gp_id, $this->merge_municipality_data);
            $vlu['can_monitoring'] = R3EcoGisGlobalPlainTableHelper::canMonitoring($this->do_id, $this->gp_id);
        }
        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Return the data for a single customer 
     */
    public function getLookupData($id = null) {
        $lkp = array();

        return $lkp;
    }

    public function getPageVars() {
        $tabMode = 'iframe';
        return array('gp_id' => $this->gp_id,
            'tab_mode' => $tabMode,
            'parent_act' => $this->parent_act,
            'short_format' => true,
            'tab_mode' => $tabMode,
            'date_format' => R3Locale::getJQueryDateFormat());
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        return array('txtModGlobalPlainSum' => _('Modifica obiettivi per settore'),
            'txtAddGlobalPlainRow' => _('Aggiungi obiettivi per settore'),
            'txtModGlobalPlainRow' => _('Modifica obiettivi per settore'),
        );
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        R3Security::checkGlobalPlain($this->gp_id);
    }

}
