<?php

require_once R3_LIB_DIR . 'r3delivery.php';
require_once R3_LIB_DIR . 'eco_simulator.php';

class R3EcoGisSimulationHelper {

    static $log = array();

    static public function getCatalogData($do_id, $mu_id, array $opt = array()) {
        static $data = null;

        $opt = array_merge(array('alternative_data_only' => false), $opt);

        if (isset($data[$do_id][$mu_id])) {
            return $data[$do_id][$mu_id];
        } else {
            $db = ezcDbInstance::get();
            $lang = R3Locale::getLanguageID();
            $mu_id = (int) $mu_id;
            $defaultOptions = array('allow_empty' => false,
                'empty_text' => _('-- Selezionare --'));
            $opt = array_merge($defaultOptions, $opt);
            $data = array();
            $sql = "SELECT ac_id, ac_code, ac_name_{$lang} AS ac_name, ac_descr_{$lang} AS ac_descr, gc_id, gc_parent_id,
      gc_name_{$lang}_parent AS gc_name_parent, gc_name_{$lang} AS gc_name, gc_extradata_{$lang} AS gc_extradata, ac_responsible_department_{$lang} AS ac_responsible_department,
      ac_start_date, ac_end_date, ac_benefit_start_date, ac_benefit_end_date, 
      CASE WHEN ac_benefit_end_date>='2020-01-01' THEN 'T' ELSE 'F' END AS ac_benefit_ok, 
      ac_estimated_auto_financing, ac_expected_renewable_energy_production,
      gpa_id, gpa_name_{$lang} AS gpa_name, gpa_extradata_{$lang} AS gpa_extradata, emo_id, ac_object_id,
      ac_expected_energy_saving_kwh/1000 AS ac_expected_energy_saving_mwh,
      ac_expected_renewable_energy_production_kwh/1000 AS ac_expected_renewable_energy_production_mwh,
      ac_expected_co2_reduction_calc/1000 AS ac_expected_co2_reduction_calc,
      ac_green_electricity_purchase_kwh/1000 AS ac_green_electricity_purchase_mwh,
      ac_green_electricity_co2_factor
      FROM ecogis.action_catalog_data 
      WHERE 1=1";
            if ($mu_id <> '') {
                $sql .= " AND mu_id={$mu_id} ";
            }
            if ($opt['alternative_data_only']) {
                $sql .= " AND ac_alternative_simulation IS TRUE ";
            }
            $sql .= "ORDER BY gc_order_parent, gc_name_parent, gc_order, gc_name, ac_name, gpa_name, ac_id";
            // echo $sql;
            foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
                $data[$row['ac_id']] = $row;
            }
        }
        return $data;
    }

    static function getGlobalStrategyList($do_id, $mu_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $opt['constraints'] = 'mu_id=' . (int) $mu_id;
        return R3Opt::getOptList('global_strategy', 'gst_id', 'gst_name_' . R3Locale::getLanguageID(), $opt);
    }

    static private function getChainDependencies($ac_id, &$listArray, $level = 0) {
        if ($level > 50) {
            // Prevent infinite loop
            throw new exception("Too many dependencies");
            return;
        }
        $db = ezcDbInstance::get();
        $sql = "SELECT ac_related_id, acd_type FROM ecogis.action_catalog_dependencies WHERE ac_id={$ac_id}";
        foreach ($db->query($sql) as $row) {
            if ($row['acd_type'] == 'R') {
                $key = 'required';
            } else if ($row['acd_type'] == 'D') {
                $key = 'related';
            } else if ($row['acd_type'] == 'E') {
                $key = 'excluded';
            } else {
                throw new exceltion('Invalid value for acd_type');
            }
            if (!in_array($row['ac_related_id'], $listArray[$key])) {
                $listArray[$key][] = $row['ac_related_id'];
                self::getChainDependencies($row['ac_related_id'], $listArray, $level + 1);
            }
        }
    }

    static public function getCatalogDataHTML($do_id, $mu_id, array $opt = array()) {
        global $smarty;
        $db = ezcDbInstance::get();

        $sql = "SELECT ac_related_id, acd_type FROM action_catalog_dependencies WHERE ac_id=?";

        $stmt = $db->prepare($sql);
        $vlu = array();
        foreach (self::getCatalogData($do_id, $mu_id, $opt) as $row) {
            $vlu[$row['gc_parent_id']]['name'] = $row['gc_name_parent'];
            $vlu[$row['gc_parent_id']]['data'][$row['ac_id']] = $row;
            $dependencies = array('related' => array(), 'required' => array(), 'excluded' => array());
            $stmt->execute(array($row['ac_id']));
            while ($row2 = $stmt->fetch(PDO::FETCH_ASSOC)) {
                if ($row2['acd_type'] == 'R') {
                    $key = 'required';
                } else if ($row2['acd_type'] == 'D') {
                    $key = 'related';
                } else if ($row2['acd_type'] == 'E') {
                    $key = 'excluded';
                } else {
                    throw new exceltion('Invalid value for acd_type');
                }
                $dependencies[$key][] = $row2['ac_related_id'];
                self::getChainDependencies($row2['ac_related_id'], $dependencies);
            }
            $vlu[$row['gc_parent_id']]['data'][$row['ac_id']]['ac_id_related_excluded'] = implode(',', $dependencies['excluded']);
            $vlu[$row['gc_parent_id']]['data'][$row['ac_id']]['ac_id_related_required'] = implode(',', $dependencies['required']);
            $vlu[$row['gc_parent_id']]['data'][$row['ac_id']]['ac_id_related'] = implode(',', $dependencies['related']);
        }

        $smarty->assign('vlu', $vlu);
        return $smarty->fetch('simulation_catalog_edit.tpl');
    }

    static public function getSelectedCatalogDataHTML($do_id, $mu_id, array $opt = array()) {
        global $smarty;

        $vlu = array();
        foreach (self::getCatalogData($do_id, $mu_id, $opt) as $row) {
            $vlu[$row['gc_parent_id']]['name'] = $row['gc_name_parent'];
            $vlu[$row['gc_parent_id']]['data'][$row['ac_id']] = $row;
        }
        $smarty->assign('vlu', $vlu);
        return $smarty->fetch('simulation_selected_edit.tpl');
    }

    static public function generatePAESFromSimulation($id) {
        $db = ezcDbInstance::get();
        $sql = "SELECT ac_id, swd_energy_efficacy FROM simulation_work_detail WHERE sw_id=" . (int) $id;
        $ac_id_list = array();
        $ac_perc_list = array();
        foreach ($db->query($sql) as $row) {
            $ac_id_list[] = $row['ac_id'];
            $ac_perc_list[] = $row['swd_energy_efficacy'];
        }
        // Master
        $sql = "INSERT INTO global_plain (mu_id, gp_name_1, gp_name_2)
                SELECT mu_id, sw_title_1, sw_title_2
                FROM simulation_work
                WHERE sw_id=" . (int) $id;
        $db->exec($sql);
        $gp_id = $db->lastInsertId('global_plain_gp_id_seq');

        // Detail
        $sql = "INSERT INTO global_plain_row (gp_id, gc_id, gc_extradata_1, gc_extradata_2, gpa_id, gpr_descr_1, gpr_descr_2, gpr_responsible_department_1,
                       gpr_responsible_department_2, gpr_start_date, gpr_end_date, gpr_estimated_cost, gpr_expected_energy_saving,
                       gpr_expected_renewable_energy_production, gpr_expected_co2_reduction, gpa_extradata_1, gpa_extradata_2)
        VALUES (:gp_id, :gc_id, :gc_extradata_1, :gc_extradata_2, :gpa_id, :gpr_descr_1, :gpr_descr_2, :gpr_responsible_department_1,
                       :gpr_responsible_department_2, :gpr_start_date, :gpr_end_date, :gpr_estimated_cost, :gpr_expected_energy_saving,
                       :gpr_expected_renewable_energy_production, :gpr_expected_co2_reduction, :gpa_extradata_1, :gpa_extradata_2)";
        $stmt = $db->prepare($sql);

        $data = R3EcoGisSimulationHelper::getSummaryTotals($id, $ac_id_list, $ac_perc_list, 1, false, true); //, $efe);
        $sql = "SELECT ac.ac_id, gc_id, gc_extradata_1, gc_extradata_2, gpa_id, ac_descr_1, ac_descr_2, ac_responsible_department_1,
                       ac_responsible_department_2, ac_start_date, ac_end_date, gpa_extradata_1, gpa_extradata_2
                FROM ecogis.simulation_work_detail swd
                INNER JOIN action_catalog_data ac ON swd.ac_id=ac.ac_id
                WHERE sw_id=" . (int) $id;

        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $values = array();
            $values[':gp_id'] = $gp_id;
            $values[':gc_id'] = $row['gc_id'];
            $values[':gc_extradata_1'] = $row['gc_extradata_1'];
            $values[':gc_extradata_2'] = $row['gc_extradata_2'];
            $values[':gpa_id'] = $row['gpa_id'];
            $values[':gpr_descr_1'] = $row['ac_descr_1'];
            $values[':gpr_descr_2'] = $row['ac_descr_2'];
            $values[':gpr_responsible_department_1'] = $row['ac_responsible_department_1'];
            $values[':gpr_responsible_department_2'] = $row['ac_responsible_department_2'];
            $values[':gpr_start_date'] = $row['ac_start_date'];
            $values[':gpr_end_date'] = $row['ac_end_date'];
            $values[':gpr_estimated_cost'] = $data['detail'][$row['gc_id']][$row['ac_id']]['estimated_cost'] == '' ? null : round($data['detail'][$row['gc_id']][$row['ac_id']]['estimated_cost'], 2);
            $values[':gpr_expected_energy_saving'] = $data['detail'][$row['gc_id']][$row['ac_id']]['energy_saving_kwh'] == '' ? null : round($data['detail'][$row['gc_id']][$row['ac_id']]['energy_saving_kwh'] / 1000, 2);
            $values[':gpr_expected_renewable_energy_production'] = $data['detail'][$row['gc_id']][$row['ac_id']]['energy_production_kwh'] == '' ? null : round($data['detail'][$row['gc_id']][$row['ac_id']]['energy_production_kwh'] / 1000, 2);
            $values[':gpr_expected_co2_reduction'] = $data['detail'][$row['gc_id']][$row['ac_id']]['energy_saving_co2'] = '' ? null : round($data['detail'][$row['gc_id']][$row['ac_id']]['energy_saving_co2'] / 1000, 2);
            $values[':gpa_extradata_1'] = $row['gpa_extradata_1'];
            $values[':gpa_extradata_2'] = $row['gpa_extradata_2'];
            $stmt->execute($values);
        }

        // Detail sum
        $sql = "INSERT INTO global_plain_sum (gp_id, gc_id, gps_expected_energy_saving, gps_expected_renewable_energy_production, gps_expected_co2_reduction)
        VALUES (:gp_id, :gc_id, :gps_expected_energy_saving, :gps_expected_renewable_energy_production, :gps_expected_co2_reduction)";
        $stmt = $db->prepare($sql);
        foreach ($data['category_sum'] as $gc_id => $row) {
            $values = array();
            $values[':gp_id'] = $gp_id;
            $values[':gc_id'] = $gc_id;
            $values[':gps_expected_energy_saving'] = $row['energy_saving_kwh'] == '' ? null : round($row['energy_saving_kwh'] / 1000, 2);
            $values[':gps_expected_renewable_energy_production'] = $row['energy_production_kwh'] == '' ? null : round($row['energy_production_kwh'] / 1000, 2);
            $values[':gps_expected_co2_reduction'] = $row['energy_saving_co2'] == '' ? null : round($row['energy_saving_co2'] / 1000, 2);
            $stmt->execute($values);
        }
        return $gp_id;
    }

    static public function getSummaryTypeList(array $opt = array()) {
        $defaultOptions = array('include_udm' => false);
        $opt = array_merge($defaultOptions, $opt);

        if ($opt['include_udm']) {
            return array('COST' => _('Costi stimati [€]'),
                'ENERGY_SAVING' => _('Risparmio energetico [MWh/a]'),
                'ENERGY_PRODUCTION' => _('Produzione di energia [MWh/a]'),
                'CO2_REDUCTION' => _('Riduzione di CO2 prevista[t/a]'));
        }
        return array('COST' => _('Costi stimati'),
            'ENERGY_SAVING' => _('Risparmio energetico'),
            'ENERGY_PRODUCTION' => _('Produzione di energia'),
            'CO2_REDUCTION' => _('Riduzione di CO2 prevista'));
    }

    static function getSummaryTable(array $idList, array $percList, $type, $tableType, $macroCategory) {
        $params = array('ac_estimated_auto_financing', 'ac_expected_energy_saving_mwh', 'ac_expected_renewable_energy_production_mwh', 'ac_expected_co2_reduction_calc_t');
        $result = array('type' => $type, 'table' => $tableType, 'macro_category' => $macroCategory,
            'summarytype_list' => self::getSummaryTypeList(array('include_udm' => true)),
        );
        $result['years'] = array();
        if (count($idList) > 0) {
            $list = array();
            for ($i = 0; $i < count($idList); $i++) {
                $list[$idList[$i]] = $percList[$i];
            }
            $in = implode(', ', $idList);
            $db = ezcDbInstance::get();
            $lang = R3Locale::getLanguageID();
            $moreWhere = '';
            if ($macroCategory <> '') {
                $moreWhere .= " AND gc_parent_id=" . (int) $macroCategory;
            }

            $sql = "SELECT MIN(least(ac_start_date, ac_benefit_start_date)) AS ac_start_date, 
                           MAX(greatest(ac_end_date, ac_benefit_end_date)) AS ac_end_date,
                           MAX(ac_benefit_end_date)-MIN(ac_benefit_start_date) AS ac_benefit_days
                FROM action_catalog_data
                WHERE ac_id IN($in) {$moreWhere}";
            list($startDate, $endDate, $benefitDays) = $db->query($sql)->fetch();
            $startYear = substr($startDate, 0, 4);
            $endYear = substr($endDate, 0, 4);
            if ($startYear == '' || $endYear == '') {
                return false;
            }

            $startYear = max(1950, substr($startDate, 0, 4));
            $endYear = min(2050, substr($endDate, 0, 4));
            for ($i = $startYear; $i <= $endYear; $i++) {
                $result['years'][$i] = (int) $i;
            }

            $sql = "SELECT gc_id, gc_parent_id, gc_name_{$lang}_parent AS gc_name_parent, gc_name_{$lang} AS gc_name, gc_extradata_{$lang} AS gc_extradata,
                           ac_start_date, ac_end_date, ac_end_date-ac_start_date AS ac_days, 
                           ac_benefit_start_date, ac_benefit_end_date, ac_benefit_end_date-ac_benefit_start_date AS ac_benefit_days, 
                           ac_name_{$lang} AS ac_name, gpa_name_{$lang} AS gpa_name, gpa_extradata_{$lang} AS gpa_extradata,
                           ac_id, ac_estimated_auto_financing, ac_expected_energy_saving_kwh, ac_expected_renewable_energy_production_kwh, ac_expected_co2_reduction_calc,
                           ac_expected_energy_saving_kwh / 1000 AS ac_expected_energy_saving_mwh,
                           ac_expected_renewable_energy_production_kwh / 1000 AS ac_expected_renewable_energy_production_mwh,
                           ac_expected_co2_reduction_calc / 1000 AS ac_expected_co2_reduction_calc_t
                    FROM action_catalog_data
                    WHERE ac_id IN($in) {$moreWhere}
                    ORDER BY gc_order_parent, gc_name_{$lang}_parent, gc_order, gc_name_{$lang}";
            $data = array('ac_estimated_auto_financing' => null, 'ac_expected_energy_saving_kwh' => null,
                'ac_expected_renewable_energy_production_kwh' => null, 'ac_expected_co2_reduction_calc' => null);
            foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
                foreach ($params as $key) {
                    if ($key <> 'ac_estimated_auto_financing') {
                        $row[$key] = round($row[$key] / 100 * $list[$row['ac_id']], 2);
                    }
                }

                $result['data'][$row['gc_parent_id']]['name'] = $row['gc_name_parent'];
                $result['data'][$row['gc_parent_id']]['data']['row'][$row['ac_id']] = $row;
                $result['data'][$row['gc_parent_id']]['data']['row'][$row['ac_id']]['year'] = self::calculateYearValues($row, $startYear, $endYear, $type);
                if ($type == 'COST') {
                    $result['data'][$row['gc_parent_id']]['data']['row'][$row['ac_id']]['tot'] = $row[self::getSummaryFieldList($type)];
                } else {
                    $result['data'][$row['gc_parent_id']]['data']['row'][$row['ac_id']]['tot'] = $row[self::getSummaryFieldList($type)]; //$row['ac_benefit_days']; // / 365 * $row[self::getSummaryFieldList($type)];
                }
                // Somme (totali)
                if (!isset($result['sum']['year'])) {
                    foreach ($result['data'][$row['gc_parent_id']]['data']['row'][$row['ac_id']]['year'] as $year => $value) {
                        foreach ($params as $key) {
                            $result['sum']['year'][$year][$key] = null;
                        }
                    }
                }
                // Somme (per categoria)
                if (!isset($result['data'][$row['gc_parent_id']]['data']['sum']['year'])) {
                    $result['data'][$row['gc_parent_id']]['tot'] = null;
                    foreach ($result['data'][$row['gc_parent_id']]['data']['row'][$row['ac_id']]['year'] as $year => $value) {
                        foreach ($params as $key) {
                            $result['data'][$row['gc_parent_id']]['data']['sum']['year'][$year][$key] = null;
                        }
                    }
                }
                foreach ($result['data'][$row['gc_parent_id']]['data']['row'][$row['ac_id']]['year'] as $year => $value) {
                    foreach ($params as $key) {
                        $result['data'][$row['gc_parent_id']]['data']['sum']['year'][$year][$key] += $value[$key];
                        $result['sum']['year'][$year][$key] += $value[$key];
                    }
                    $result['data'][$row['gc_parent_id']]['data']['sum']['year'][$year]['value'] = R3NumberFormat($result['data'][$row['gc_parent_id']]['data']['sum']['year'][$year][self::getSummaryFieldList($type)], 2, true);
                    $result['sum']['year'][$year]['value'] = R3NumberFormat($result['sum']['year'][$year][self::getSummaryFieldList($type)], 2, true);
                }
                $result['data'][$row['gc_parent_id']]['tot'] += $result['data'][$row['gc_parent_id']]['data']['row'][$row['ac_id']]['tot']; // $row[self::getSummaryFieldList($type)];
            }
            // Calcola totalone + formatta numeri
            $tot = 0;
            foreach ($result['data'] as $key => $val) {
                $tot += $val['tot'];
                $result['data'][$key]['tot'] = R3NumberFormat($val['tot'], 2, true);
                foreach ($val['data']['row'] as $key2 => $val2) {
                    $result['data'][$key]['data']['row'][$key2]['tot'] = R3NumberFormat($val2['tot'], 2, true);
                }
            }
            $result['avg'] = $benefitDays > 0 ? R3NumberFormat($tot / $benefitDays * 365, 2, true) : null;
            $result['tot'] = R3NumberFormat($tot, 2, true);
        }
        return $result;
    }

    static function calculateYearValues(array $row, $startYear, $endYear, $type) {
        $startDateField = $type == 'COST' ? 'ac_start_date' : 'ac_benefit_start_date';
        $endDateField = $type == 'COST' ? 'ac_end_date' : 'ac_benefit_end_date';
        $daysField = $type == 'COST' ? 'ac_days' : 'ac_benefit_days';

        $ac_id = (int) $row['ac_id'];
        $lastBenefit = 100;
        if ($type <> 'COST') {
            // Ricavo gli anni dei benefici
            $db = ezcDbInstance::get();
            $benefitYears = array();
            $sql = "SELECT acby_year, acby_benefit
                        FROM action_catalog_benefit_year
                        WHERE ac_id={$ac_id}
                        ORDER BY ac_id, acby_year";
            $tot = 0;
            foreach ($db->query($sql, PDO::FETCH_ASSOC) as $benefitRow) {
                if ($tot == 0) {
                    $lastBenefit = $benefitRow['acby_benefit'];
                }

                $benefitYears[$benefitRow['acby_year']] = $benefitRow['acby_benefit'];
            }
        }

        $params = array('ac_estimated_auto_financing', 'ac_expected_energy_saving_mwh', 'ac_expected_renewable_energy_production_mwh', 'ac_expected_co2_reduction_calc_t');
        $rowStartYear = substr($row[$startDateField], 0, 4);
        $rowEndYear = substr($row[$endDateField], 0, 4);
        $result = array();
        if ($rowStartYear == '' || $rowEndYear == '') {
            for ($i = $startYear; $i <= $endYear; $i++) {
                foreach ($params as $key) {
                    $result[$i][$key] = null;
                }
                $result[$i]['value'] = null;
            }
            return $result;
        }

        for ($i = $startYear; $i <= $endYear; $i++) {
            foreach ($params as $key) {
                $result[$i][$key] = null;
            }
            $result[$i]['value'] = null;
        }
        if ($rowStartYear == $rowEndYear) {
            foreach ($params as $key) {
                $result[$rowStartYear][$key] = $row[$key];
            }
            $result[$rowStartYear]['value'] = R3NumberFormat($result[$rowStartYear][self::getSummaryFieldList($type)], 2, true);
        } else {
            for ($i = $rowStartYear; $i <= $rowEndYear; $i++) {
                if ($i == $rowStartYear) {
                    $days = date("z", mktime(0, 0, 0, 12, 31, $i)) - date("z", mktime(0, 0, 0, substr($row[$startDateField], 5, 2), substr($row[$startDateField], 8, 2), $i)) + 1;
                } else if ($i == $rowEndYear) {
                    $days = date("z", mktime(0, 0, 0, substr($row[$endDateField], 5, 2), substr($row[$endDateField], 8, 2), $i)) + 1;
                } else {
                    $days = date("z", mktime(0, 0, 0, 12, 31, $i)) + 1;
                }
                foreach ($params as $key) {
                    $value = $row[$key];
                    if ($type <> 'COST') {
                        if (isset($benefitYears[$i])) {
                            $lastBenefit = $benefitYears[$i];
                        }
                        $days = min($days, 365);        // Max 1 anno
                        $value = $value / 365 * $days;  // base giornaliera
                        $value = $value / 100 * $lastBenefit;
                    } else {
                        $value = ($row[$key] / ($row[$daysField] + 1) * $days);
                    }
                    $result[$i][$key] = $value;
                }
                $result[$i]['value'] = R3NumberFormat($result[$i][self::getSummaryFieldList($type)], 2, true);
            }
        }
        return $result;
    }

    static public function getSummaryFieldList($type = null) {
        $values = array('COST' => 'ac_estimated_auto_financing',
            'ENERGY_SAVING' => 'ac_expected_energy_saving_mwh',
            'ENERGY_PRODUCTION' => 'ac_expected_renewable_energy_production_mwh',
            'CO2_REDUCTION' => 'ac_expected_co2_reduction_calc_t');
        if ($type === null) {
            return $values;
        }
        return $values[$type];
    }

    static function getSummaryTableHTML(array $idList, array $percList, $type, $tableType, $macroCategory) {
        global $smarty;

        if (count($idList) == 0) {
            return _('Dati insufficienti per effettuare un riepilogo. Selezionare una o più azioni');
        }
        $vlu = self::getSummaryTable($idList, $percList, $type, $tableType, $macroCategory);
        if ($vlu === false) {
            return _('Dati insufficienti per effettuare un riepilogo. Verificare date attuazione e benefici.');
        }
        $smarty->assign('vlu', $vlu);
        return $smarty->fetch('simulation_summary_edit.tpl');
    }

    static function getSummaryTotals($sw_id, array $idList = array(), array $percList = array(), $factor = 1, $returnAsLocale = false, $returnGlobalData = false, $efe1 = null, $efe2 = null) { //, $efe=null) { //, $efe2=null) {
        $simulator = new R3Simulator();

        $simulator->loadDataFromSimulation($sw_id);

        $simulationNo = 0;
        foreach ($idList as $ac_id) {
            $simulator->loadSimulationData($ac_id, $percList[$simulationNo]);
            $simulationNo++;
        }
        $simulator->calculate();
        self::$log = $simulator->getLog();

        if ($returnGlobalData) {
            // Qui per generazione paes
            $data = $simulator->getGlobalSimulationData();
        } else {
            $data = $simulator->getData($factor, $returnAsLocale);
        }
        return $data;
    }

    static function getInventoryTotals($ge_id, array $opt = array(), array $type = array('EMISSION'), $divider = 1000) { //$ge_id_2=null, array $type=array('EMISSION'), $divider=1000) {
        $result = array();
        R3EcoGisHelper::includeHelperClass('obj.global_result_table.php');

        foreach ($type as $t) {
            $result[$t] = R3EcoGisGlobalTableHelper::getCategoriesData($ge_id, $t, $divider);
            // Remove unused data
            if (isset($result[$t]['data'])) {
                unset($result[$t]['data']);
            }
            if (isset($result[$t]['sum']['source'])) {
                unset($result[$t]['sum']['source']);
            }

            // Calcolo pro capite
            if (isset($opt['citizen']) && $opt['citizen'] > 0) {
                $result[$t]['sum']['total_citizen'] = $result[$t]['sum']['total'] / $opt['citizen'];
            }
        }
        return $result;
    }

    static public function getLog($asHTML = false) {
        if ($asHTML) {
            $result = array();
            $lastLevel = 0;
            foreach (self::$log as $entry) {
                //$pad = str_pad('', $entry['level'] * 3, ".", STR_PAD_LEFT);
                if ($entry['level'] < $lastLevel) {
                    if ($lastLevel > 0) {
                        $result[] = "</ul>";
                    }
                    $lastLevel = $entry['level'];
                }
                if ($entry['level'] > $lastLevel) {
                    if ($lastLevel > 0) {
                        $result[] = "<ul>";
                    }
                    $lastLevel = $entry['level'];
                }
                if ($lastLevel > 0) {
                    $result[] = "<li>{$entry['text']}</li>";
                } else {
                    $result[] = "{$entry['text']}<br />";
                }
            }
            while ($lastLevel >= 0) {
                $result[] = "</ul>";
                $lastLevel--;
            }
            return implode("\n", $result);
        }
        return self::$log;
    }

    static public function clearLog() {
        self::$log = array();
    }

    static public function addLog($level, $text) {
        self::$log[] = array('level' => $level, 'text' => $text);
    }

}

class eco_simulation extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'simulation_work';

    /**
     * ecogis.simulation_work fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'sw_id', 'type' => 'integer', 'label' => _('PK'), 'is_primary_key' => true),
            array('name' => 'mu_id', 'type' => 'lookup', 'label' => _('Comune'), 'lookup' => array('table' => 'municipality')),
            array('name' => 'gst_id', 'type' => 'lookup', 'required' => false, 'label' => _('Parametri principali'), 'lookup' => array('table' => 'global_strategy')),
            array('name' => 'us_id', 'type' => 'integer', 'label' => _('Utente')),
            array('name' => 'sw_title_1', 'type' => 'text', 'required' => true, 'label' => _('Titolo')),
            array('name' => 'sw_title_2', 'type' => 'text', 'label' => _('Titolo')),
            array('name' => 'sw_descr_1', 'type' => 'text', 'label' => _('Descrizione')),
            array('name' => 'sw_descr_2', 'type' => 'text', 'label' => _('Descrizione')),
            array('name' => 'sw_efe_1', 'type' => 'float', 'label' => _('EFE')),
            array('name' => 'sw_efe_2', 'type' => 'float', 'label' => _('EFE')),
            array('name' => 'sw_efe_is_calculated', 'type' => 'boolean', 'default' => false),
            array('name' => 'sw_alternative_simulation', 'type' => 'boolean'),
        );
        return $fields;
    }

    /**
     * Fast cache field definitions
     */
    protected function defCacheFields() {
        $fieldsDef = array('swc_estimated_cost_tot',
            'swc_expected_energy_saving_tot',
            'swc_expected_renewable_energy_production_tot',
            'swc_expected_co2_reduction_tot');
        return $fieldsDef;
    }

    /**
     * Return the fields for the list table
     */
    public function getTableColumnConfig() {

        $showMunicipality = R3EcoGisHelper::getMunicipalityCount($this->do_id) > 1;
        $rows = array('mu_name' => array('label' => R3EcoGisHelper::geti18nMunicipalityLabel($this->do_id), 'visible' => $showMunicipality, 'options' => array('order_fields' => 'mu_type, mu_name, sw_title, sw_id')),
            'sw_title' => array('label' => _('Titolo'), 'options' => array('order_fields' => 'sw_title, mu_name, sw_id')),
            //'sw_date' => array('label' => _('Data'), 'type' => 'date', 'width' => 120, 'options' => array('align' => 'center', 'order_fields' => 'sw_date, sw_title, mu_name, sw_id')),
            'sw_descr' => array('label' => _('Note'), 'visible' => false),
            'sw_tot' => array('label' => _('N° interventi'), 'width' => 50, 'type' => 'integer', 'options' => array('align' => 'right', 'order_fields' => 'sw_tot, sw_title, mu_name, sw_id')),
            'sw_efe_1' => array('label' => _('EFE'), 'width' => 50, 'type' => 'float', 'options' => array('align' => 'right', 'order_fields' => 'sw_efe_1, sw_title, mu_name, sw_id', 'number_format' => array('decimals' => 3))),
            'swc_estimated_cost_tot' => array('label' => _('Costi totali stimati'), 'width' => 100, 'type' => 'float', 'options' => array('align' => 'right', 'order_fields' => 'swc_estimated_cost_tot, sw_id', 'number_format' => array('decimals' => 2))),
            'swc_expected_energy_saving_tot' => array('label' => _('Risparmio energetico totale [MWh/a]'), 'width' => 100, 'type' => 'float', 'options' => array('align' => 'right', 'order_fields' => 'swc_expected_energy_saving_tot, sw_id', 'number_format' => array('decimals' => 0))),
            'swc_expected_renewable_energy_production_tot' => array('label' => _('Produzione di energia totale [MWh/a]'), 'width' => 100, 'type' => 'float', 'options' => array('align' => 'right', 'order_fields' => 'swc_expected_renewable_energy_production_tot, sw_id', 'number_format' => array('decimals' => 0))),
            'swc_expected_co2_reduction_tot' => array('label' => _('Riduzione di CO2 totale [t/a]'), 'width' => 100, 'type' => 'float', 'options' => array('align' => 'right', 'order_fields' => 'swc_expected_co2_reduction_tot, sw_id', 'number_format' => array('decimals' => 0))),
        );
        if (R3AuthInstance::get()->getParam('mu_id') <> '') {
            unset($rows['mu_name']);
        }
        return $rows;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        $this->fields = $this->defFields();
        $storeVar = isset($_GET['act']) && $_GET['act'] == 'list';  // if true store the filter variables

        $this->alternativeSimulation = false;  // Simulazione su tutti gli oggetti e non solo quelli comunali

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        if ($init || $reset) {
            $storeVar = true;
        }
        $this->id = initVar('id');
        $this->last_id = initVar('last_id');
        $this->act = initVar('act', 'list');
        $this->select_done = initVar('select_done');

        $this->do_id = $_SESSION['do_id'];
        $this->pr_id = PageVar('pr_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->sw_title = PageVar('sw_title', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->sw_date_from = forceISODate(PageVar('sw_date_from', null, $init | $reset, false, $this->baseName, $storeVar));
        $this->sw_date_to = forceISODate(PageVar('sw_date_to', null, $init | $reset, false, $this->baseName, $storeVar));

        $this->order = PageVar('order', '1A', $init, false, $this->baseName, $storeVar);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName, $storeVar);

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('calculateTotals');
        $this->registerAjaxFunction('recalculateSimulation');
        $this->registerAjaxFunction('getSummaryTable');
        $this->registerAjaxFunction('askDelSimulation');
        $this->registerAjaxFunction('getGlobalStrategy');
        $this->registerAjaxFunction('submitFormData');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuova simulazione');
            case 'mod': return _('Modifica simulazione');
            case 'show': return _('Visualizza simulazione');
            case 'list': return _('Elenco simulazioni');
        }
        return '';  // Unknown title
    }

    /**
     * Return the filter values (list form)
     */
    public function getFilterValues() {
        $filters = array();
        $filters = array();
        if ($this->auth->getParam('mu_id') == '') {
            $filters['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id, array('join_with_simulation' => true));
            $filters['mu_values'] = R3EcoGisHelper::getMunicipalityAndMunicipalityCollectionList($this->do_id, null, null, array('join_with_simulation' => true));
        } else {
            $filters['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        if (count($filters['mu_values']) == 1) {
            $mu_id = key($filters['mu_values']);
            $filters['fr_values'] = R3EcoGisHelper::getFractionList($this->do_id, $mu_id, array('used_by' => 'building'));
            $filters['st_values'] = R3EcoGisHelper::getStreetList($this->do_id, $mu_id, array('used_by' => 'building'));
        } else {
            $mu_id = null;
        }
        $filters['do_id'] = $this->do_id;
        $filters['pr_id'] = $this->pr_id;
        $filters['mu_id'] = $this->mu_id;
        $filters['mu_name'] = $this->mu_name;
        $filters['sw_title'] = $this->sw_title;
        $filters['sw_date_from'] = SQLDateToStr($this->sw_date_from, R3Locale::getPhpDateFormat());
        $filters['sw_date_to'] = SQLDateToStr($this->sw_date_to, R3Locale::getPhpDateFormat());

        return $filters;
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $where = array('(us_id IS NULL OR us_id=' . (int) $this->auth->getUID() . ')');
        $q = $db->createSelectQuery();
        $where[] = $q->expr->eq('do_id', $this->do_id);
        if (!$this->auth->hasPerm('SHOW', 'ALL_DOMAINS') && $this->auth->getParam('mu_id') <> '') {
            $where[] = $q->expr->eq('mu_id', $db->quote((int) $this->auth->getParam('mu_id')));
        }
        if ($this->auth->getParam('mu_id') <> '') {
            $where[] = $q->expr->eq('mu_id', $this->auth->getParam('mu_id'));
        }
        if ($this->pr_id <> '') {
            $where[] = $q->expr->eq('pr_id', $db->quote((int) $this->pr_id));
        }
        if ($this->mu_id <> '') {
            $where[] = $q->expr->eq('mu_id', $db->quote((int) $this->mu_id));
        }
        if ($this->mu_name <> '') {
            $where[] = "mu_name_{$lang} ILIKE " . $db->quote("%{$this->mu_name}%");
        }
        if ($this->sw_title <> '') {
            $where[] = "sw_title_{$lang} ILIKE " . $db->quote("%{$this->sw_title}%");
        }

        if ($this->sw_title <> '') {
            $where[] = "sw_title_{$lang} ILIKE " . $db->quote("%{$this->sw_title}%");
        }
        $where[] = "sw_alternative_simulation IS " . ($this->alternativeSimulation ? 'TRUE' : 'FALSE');

        $q->select("sw.sw_id, mu_name_$lang AS mu_name, sw_title_$lang AS sw_title, 
                    SUBSTR(sw_descr_$lang, 1, 100) AS sw_descr, sw_tot, sw_efe_1, sw_efe_2, sw_efe_is_calculated,
                    sw_visible, sw_invalid, swc_estimated_cost_tot, swc_expected_energy_saving_tot/1000 AS swc_expected_energy_saving_tot,
                    swc_expected_renewable_energy_production_tot/1000 AS swc_expected_renewable_energy_production_tot, swc_expected_co2_reduction_tot/1000 AS swc_expected_co2_reduction_tot")
                ->from('ecogis.simulation_work_data sw')
                ->leftJoin('ecogis.simulation_work_cache swc', 'sw.sw_id=swc.sw_id');
        if (count($where) > 0) {
            $q->where($where);
        }
        return $q;
    }

    private function hasInvalidSimulation() {
        $db = ezcDbInstance::get();
        $sql = $this->getListSQL();
        $sql = "SELECT COUNT(*) FROM ({$sql}) AS foo WHERE sw_invalid > 0";
        return $db->query($sql)->fetchColumn() > 0;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    private $columnTypes = array();  // Porta avanti i tipi di colonna per la formattazione

    public function createListTableHeader(&$order) {
        $this->columnTypes = array();
        $tableConfig = new R3BaseTableConfig(R3AuthInstance::get());
        $tableColumns = $tableConfig->getConfig($this->getTableColumnConfig(), $this->baseName);
        foreach ($tableColumns as $fieldName => $colDef) {
            $this->columnTypes[$fieldName] = array('type' => strtolower($colDef['type']), 'options' => $colDef['options']);
            if ($colDef['visible']) {
                $this->simpleTable->addSimpleField($colDef['label'], $fieldName, 'CALCULATED', $colDef['width'], $colDef['options']);
            }
        }
        if ($this->hasInvalidSimulation()) {
            $this->simpleTable->addSimpleField(_('Stato'), 'status', 'CALCULATED', 100);
        }
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 85);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order, 'global_strategy_list_table');
    }

    public function getListTableRowOperations(&$row) {

        if ($row['sw_invalid']) {
            $this->simpleTable->AddCalcValue('status', "<span class='invalid_simulation' data-role='{$row['sw_id']}'>" . _('Ricalcolo...') . "</span>");
        } else {
            $this->simpleTable->AddCalcValue('status', 'OK');
        }
        $id = $row['sw_id'];
        foreach ($row as $fieldName => $fieldValue) {
            if ($fieldValue <> '' && isset($this->columnTypes[$fieldName])) {
                switch ($this->columnTypes[$fieldName]['type']) {
                    case 'integer':
                        $fieldValue = number_format($fieldValue, 0, ',', '.');
                        break;
                    case 'float':
                        $fieldValue = number_format($fieldValue, $this->columnTypes[$fieldName]['options']['number_format']['decimals'], ',', '.');
                        break;
                }
            }
            $this->simpleTable->AddCalcValue($fieldName, "<span class='simulation_td' data-role='{$id}_{$fieldName}'>{$fieldValue}</span>"); //(_('<b>Ricalcolo...');
        }

        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'show':
                        $links['SHOW'] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:showObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'del':
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelSimulation('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                }
            }
        }
        return $links;
    }

    public function getListTableRowStyle(&$row) {
        if ($row['sw_id'] == $this->last_id) {
            return array('normal' => 'selected_row');
        }
        return array();
    }

    /**
     * Return the data for a single customer
     */
    public function getData($id = null) {

        $lang = R3Locale::getLanguageID();
        if ($id === null) {
            $id = $this->id;
        }
        if ($this->auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
            $do_id = $_SESSION['do_id'];
        } else {
            $do_id = $this->auth->getDomainID();
        }
        $opt = array();
        $db = ezcDbInstance::get();
        R3EcoGisSimulationHelper::clearLog();

        if ($this->act <> 'add') {
            if ($id === null) {
                throw new Exception("Missing ID for customer");
            }
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = 'sw_id=' . (int) $id;
            $where[] = '(us_id IS NULL OR us_id=' . $this->auth->getUID() . ')';
            if (!$this->auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
                $where[] = $q->expr->eq('do_id', (int) $this->auth->getDomainID());
            }
            $q->select("*, ge_name_{$lang} AS ge_name, ge_2_name_{$lang} AS ge_2_name")
                    ->from('simulation_work_data')
                    ->where($where);
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $vlu['mu_name'] = $vlu['mu_name_' . $lang];

            R3EcoGisSimulationHelper::addLog(0, _('Riepilogo calcolo'));
            $sql = "SELECT ac_id, swd_energy_efficacy FROM simulation_work_detail WHERE sw_id=" . (int) $id;
            $ac_id_list = array();
            $ac_perc_list = array();
            foreach ($db->query($sql) as $row) {
                $ac_id_list[] = $row['ac_id'];
                $ac_perc_list[] = $row['swd_energy_efficacy'];
            }
            $vlu['actions'] = $this->getSimulationWork($id, $this->select_done == 'T');
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('simulation_work', $vlu['sw_id']));
        } else {
            $vlu = array();
            $vlu['mu_id'] = '';
            $mu_values = R3EcoGisHelper::getMunicipalityList($this->do_id);
            if (count($mu_values) == 1) {
                $vlu['mu_id'] = key($mu_values);
            } else if ($this->auth->getParam('mu_id') > 0) {
                $vlu['mu_id'] = $this->auth->getParam('mu_id');
            }
            if ($vlu['mu_id'] <> '') {
                $q = $db->createSelectQuery();
                $q->select("gst_id ")
                        ->from('global_strategy')
                        ->where('mu_id=' . (int) $vlu['mu_id']);
                $vlu['gst_id'] = $db->query($q)->fetch();
            }
            if ($this->alternativeSimulation) {
                $vlu['sw_title_1'] = sprintf(_('Simulazione comunale del %s'), date('d/m/Y'));
                $vlu['sw_title_2'] = sprintf(_('Simulazione comunale del %s'), date('d/m/Y'));
            } else {
                $vlu['sw_title_1'] = sprintf(_('Simulazione del %s'), date('d/m/Y'));
                $vlu['sw_title_2'] = sprintf(_('Simulazione del %s'), date('d/m/Y'));
            }
            $vlu['sw_efe_is_calculated'] = 'T';
        }

        $this->data = $vlu; // Save the data (prevent multiple sql)

        return $vlu;
    }

    /**
     * Return the data for a single customer
     */
    public function getLookupData($id = null) {
        $lkp = array();
        if ($this->auth->getParam('mu_id') == '') {
            $lkp['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id);
            $lkp['mu_values'] = R3EcoGisHelper::getMunicipalityAndMunicipalityCollectionList($this->do_id);
        } else {
            $lkp['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        $mu_id = $this->auth->getParam('mu_id');
        if ($this->act == 'add' && count($lkp['mu_values']['data']) == 1) {
            $mu_id = key($lkp['mu_values']['data']);
        } else if ($this->act == 'mod' || $this->act == 'show') {
            $mu_id = $this->data['mu_id'];
        }
        $lkp['global_strategy_list'] = R3EcoGisSimulationHelper::getGlobalStrategyList($this->do_id, $mu_id);
        $lkp['summary_type_list'] = R3EcoGisSimulationHelper::getSummaryTypeList();
        $lkp['summary_table_list'] = array('NORMAL' => _('Espansa'),
            'GROUPED' => _('Raggruppata'));
        $lkp['do_id'] = $this->do_id;
        $lkp['pr_id'] = $this->pr_id;
        $lkp['mu_id'] = $this->mu_id;

        return $lkp;
    }

    public function getJSFiles() {
    }

    public function getJSVars() {
        return array('txtLoading' => _('Caricamento in corso. Attendere..'),
            'txtRelatedActionSelected' => _("Assieme all'azione selezionata, si consiglia anche di attivare le azioni sottostanti. Si desidera attivarle?"),
            'txtRelatedRequiredActionSelected' => _('Le seguenti azioni propedeutiche correlate sono state automaticamente selezionate:'),
            'txtRelatedExcludedActionSelected' => _('Le seguenti azioni esclusive correlate sono state automaticamente deselezionate:'),
            'txtCompareTable' => _('Confronto simulationi'),
            'txtAskGeneratePaes' => _('Sei sicuro di voler salvare e generare un piano di azione da questa simulazione?'),
            'txtAskGotoPaes' => _('Il piano di azione è stato generato correttamente. Si desidera visualizzarlo?'),
            'txtNoGlobalParametersDefined' => _('Attenzione, per questo comune non sono stati impostati i parametri principali. Non sarà possibile quindi salvare i dati'),
            'txtGlobalStrategyChange' => _("Attenzione!\\nE' necessario salvare i dati per aggiornare correttamente le statistiche."),
            'txtAutoSelectedActions' => _("Attenzione!\\nGli interventi già conclusi sono stati selezionati automaticamente."));
    }

    public function getPageVars() {
        if ($this->act != 'list') {
            $mu_id = $this->auth->getParam('mu_id');
            if ($this->act == 'add') {
                $mu_values = R3EcoGisHelper::getMunicipalityList($this->do_id);
                if (count($mu_values) == 1) {
                    $mu_id = key($mu_values);
                }
            } else if ($this->act == 'mod' || $this->act == 'show') {
                $mu_id = $this->data['mu_id'];
            }
            if ($mu_id > 0) {
                $autoSelectedActions = $this->select_done == 'T' && $this->data['actions']['ac_id_list'] <> '';
                return array('catalog_html' => R3EcoGisSimulationHelper::getCatalogDataHTML($this->do_id, $mu_id, array('alternative_data_only' => $this->alternativeSimulation)),
                    'selected_html' => R3EcoGisSimulationHelper::getSelectedCatalogDataHTML($this->do_id, $mu_id),
                    'auto_selected_actions' => $autoSelectedActions);
            }
        }
        return array();
    }

    /**
     * Save the simulation work data by delete-insert
     * @param array $request   the request
     * @return array           ajax format status
     */
    protected function updateSimulationWork($sw_id, array $ac_id_list = array(), $ac_perc_list = array()) {
        $tot = 0;
        $db = ezcDbInstance::get();
        $sql = "DELETE FROM simulation_work_detail WHERE sw_id=?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array($sw_id));
        if (count($ac_id_list) > 0) {
            $list = array();
            for ($i = 0; $i < count($ac_id_list); $i++) {
                $list[$ac_id_list[$i]] = $ac_perc_list[$i];
            }
            $sql = "INSERT INTO simulation_work_detail (sw_id, ac_id, swd_energy_efficacy) VALUES (?, ?, ?)";
            $stmt = $db->prepare($sql);
            foreach ($list as $ac_id => $perc) {
                $stmt->execute(array($sw_id, $ac_id, $perc));
            }
        }
        return $tot;
    }

    protected function getSimulationWork($id, $selectDone = false) {
        $db = ezcDbInstance::get();
        $id = (int) $id;
        $sql = "SELECT ac_id, swd_energy_efficacy FROM simulation_work_detail WHERE sw_id={$id}";
        $ac_id_list = array();
        $ac_perc_list = array();
        foreach ($db->query($sql) as $row) {
            $ac_id_list[] = $row['ac_id'];
            $ac_perc_list[] = $row['swd_energy_efficacy'];
        }

        if ($selectDone) {
            $mu_id = $db->query("SELECT mu_id FROM simulation_work WHERE sw_id={$id}")->fetchColumn();
            $data = R3EcoGisSimulationHelper::getCatalogData($this->do_id, $mu_id);
            foreach ($data as $row) {
                if ($row['ac_benefit_ok'] == 'T' && $row['ac_end_date'] <> '' & $row['ac_end_date'] <= date('Y-m-d')) {
                    $ac_id_list[] = $row['ac_id'];
                    $ac_perc_list[] = 100;
                }
            }
        }
        $result = array('ac_id_list' => implode(',', $ac_id_list), 'ac_perc_list' => implode(',', $ac_perc_list));
        return $result;
    }

    function updateCacheSimulationData($id) {
        $db = ezcDbInstance::get();

        $oldLocale = setlocale(LC_ALL, 0);
        setlocale(LC_ALL, 'C');
        $sql = "SELECT sw_invalid FROM simulation_work WHERE sw_id=" . (int) $id;
        $sw_id_start = $db->query($sql)->fetchColumn();

        $sql = "SELECT ac_id, swd_energy_efficacy FROM simulation_work_detail WHERE sw_id=" . (int) $id;
        $ac_id_list = array();
        $ac_perc_list = array();
        foreach ($db->query($sql) as $row) {
            $ac_id_list[] = $row['ac_id'];
            $ac_perc_list[] = $row['swd_energy_efficacy'];
        }
        $data = R3EcoGisSimulationHelper::getSummaryTotals($id, $ac_id_list, $ac_perc_list, 1, false); //, $efe);
        $cacheData = array();
        if ($data['simulation'][1]['present'] == true) {
            $cacheData['swc_estimated_cost_tot'] = $data['simulation'][1]['cost']['total'];
            $cacheData['swc_expected_energy_saving_tot'] = $data['simulation'][1]['energy_saving']['total'];
            $cacheData['swc_expected_renewable_energy_production_tot'] = $data['simulation'][1]['renewal_production']['total'];
            $cacheData['swc_expected_co2_reduction_tot'] = $data['simulation'][1]['simulation_reduction']['total'];
        }
        // Delete old cache date
        $sql = "DELETE FROM simulation_work_cache WHERE sw_id=" . (int) $id;
        $db->exec($sql);

        $fieldsDef = $this->defCacheFields();

        $values = array();
        $fields = array();

        foreach ($fieldsDef as $fieldName) {
            $fields[] = $fieldName;
            if (!isset($cacheData[$fieldName]) || $cacheData[$fieldName] == '') {
                $values[] = 'NULL';
            } else {
                $values[] = $cacheData[$fieldName];
            }
        }
        $sql = "INSERT INTO simulation_work_cache (sw_id, " .
                implode(', ', $fields) . " " .
                ") VALUES ({$id}, " .
                implode(', ', $values) . ")";

        $db->exec($sql);
        for ($i = 1; $i <= 2; $i++) {
            if ($data['simulation'][$i]['present']) {
                $sql = "UPDATE simulation_work SET sw_efe_{$i}=? WHERE sw_id=?";
                $stmt = $db->prepare($sql);
                $stmt->execute(array(sprintf('%.3f', $data['simulation'][$i]['efe']), $id));
            }
        }

        $sql = "UPDATE simulation_work SET sw_invalid=GREATEST(0, sw_invalid-{$sw_id_start}) WHERE sw_id=?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array($id));

        setlocale(LC_ALL, $oldLocale);
        return $db->lastInsertId('simulation_work_cache_swc_id_seq');
    }

    public function municipalityHasCitizen($mu_id) {
        $db = ezcDbInstance::get();
        $mu_id = (int) $mu_id;
        $sql = "SELECT gst_reduction_target_citizen
                FROM ecogis.global_strategy
                WHERE mu_id={$mu_id}";
        return $db->query($sql)->fetchColumn() > 0;
    }

    public function getInventoryCitizen($mu_id, $inventoryId) {
        $db = ezcDbInstance::get();
        $mu_id = (int) $mu_id;
        $field = $inventoryId == 1 ? 'ge_id' : 'ge_id_2';

        $sql = "SELECT ge_citizen
                FROM ecogis.global_strategy gs
                INNER JOIN ecogis.global_entry ge ON gs.{$field}=ge.ge_id
                WHERE gs.mu_id={$mu_id}";
        $tot = $db->query($sql)->fetchColumn();
        if ($tot === false) {
            return false;  // Inventory not found
        }
        return $tot;
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $db = ezcDbInstance::get();

        $errors = array();
        $request['sw_id'] = forceInteger($request['id'], 0, false, '.');
        if ($this->act <> 'del') {
            $errors = $this->checkFormData($request, $errors);
            $request['mu_id'] = $this->checkFormDataForMunicipality($request, $errors);
            if ($request['mu_id'] <> '' && !$this->municipalityHasCitizen($request['mu_id'])) {
                $errors['mu_id'] = array('CUSTOM_ERROR' => _('Impostare nei parametri principali gli abitanti al 2020'));
            }
            for ($i = 1; $i <= 2; $i++) {
                $citizen = $this->getInventoryCitizen($request['mu_id'], $i);
                if ($request['mu_id'] <> '' && $citizen !== false && !($citizen > 0)) {
                    $errors['mu_id'] = array('CUSTOM_ERROR' => sprintf(_("Impostare il numero di abitanti nell'inventario %d"), $i));
                }
            }
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            ignore_user_abort(true);
            $db->beginTransaction();

            $request['sw_alternative_simulation'] = $this->alternativeSimulation ? 'T' : 'F';  // Force simulation parameter
            $id = $this->applyData($request);
            if ($this->act == 'del') {
                $this->updateSimulationWork($id);
            } else {
                // Save checked actions
                $this->updateSimulationWork($id, explodeInt($request['ac_id_list']), explodeInt($request['ac_perc_list']));
                // Save cache data
                $this->updateCacheSimulationData($id);
            }
            if (isset($request['generate_paes']) && $request['generate_paes'] == 'T') {
                $gp_id = R3EcoGisSimulationHelper::generatePAESFromSimulation($id);
            } else {
                $gp_id = 0;
            }
            $db->commit();
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneSimulation({$id}, $gp_id)");
        }
    }

    public function getGlobalStrategy($request) {
        $data = R3EcoGisSimulationHelper::getGlobalStrategyList($this->do_id, $request['mu_id']);
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => $data);
    }

    // Converte array di array in array con chiave unica
    private function adjSummaryData($data, &$result, $keyName = null) {
        foreach ($data as $key => $val) {
            $keyBaseName = $keyName === null ? $key : "{$keyName}_{$key}";
            if (is_array($val)) {
                $this->adjSummaryData($val, $result, $keyBaseName);
            } else {
                $result[$keyBaseName] = $val;
            }
        }
    }

    public static function closeSession() {
        // Prevent application block for long request
        $sessionData = $_SESSION;
        session_write_close();
        $_SESSION = $sessionData;
        return $_SESSION;
    }

    public function calculateTotals($request) {

        $id = (int) $request['id'];
        $efe1 = null;
        $efe2 = null;

        $_SESSION = $this->closeSession();

        R3EcoGisSimulationHelper::clearLog();
        $data = R3EcoGisSimulationHelper::getSummaryTotals($id, explodeInt($request['ac_id_list']), explodeInt($request['ac_perc_list']), 1000, true, false, $efe1, $efe2);
        $log = R3EcoGisSimulationHelper::getLog(true);


        $message = '';
        if (isset($data['simulation'][2]['efe']) && ($data['simulation'][2]['efe'] < 0.1 || $data['simulation'][2]['efe'] > 1)) {
            $message = _("ATTENZIONE! Il valore dell'EFE sembra essere fuori soglia. Verificare i parametri");
        }
        if (isset($data['simulation'][1]['efe']) && ($data['simulation'][1]['efe'] < 0.1 || $data['simulation'][1]['efe'] > 1)) {
            $message = _("ATTENZIONE! Il valore dell'EFE sembra essere fuori soglia. Verificare i parametri");
        }
        $result = array();
        $this->adjSummaryData($data, $result);

        return array('status' => R3_AJAX_NO_ERROR,
            'efe1' => isset($data['simulation'][1]['efe']) ? R3NumberFormat($data['simulation'][1]['efe'], 3) : '',
            'efe2' => isset($data['simulation'][2]['efe']) ? R3NumberFormat($data['simulation'][2]['efe'], 3) : '',
            'goal1_reached_total' => isset($data['simulation'][1]['goal_reached']['total']) ? $data['simulation'][1]['goal_reached']['total'] : false,
            'goal2_reached_total' => isset($data['simulation'][2]['goal_reached']['total']) ? $data['simulation'][2]['goal_reached']['total'] : false,
            'goal1_reached_per_capita' => isset($data['simulation'][1]['goal_reached']['per_capita']) ? $data['simulation'][1]['goal_reached']['per_capita'] : false,
            'goal2_reached_per_capita' => isset($data['simulation'][2]['goal_reached']['per_capita']) ? $data['simulation'][2]['goal_reached']['per_capita'] : false,
            'message' => $message,
            'log' => $log,
            'data' => $result);
    }

    public function recalculateSimulation($request) {
        $db = ezcDbInstance::get();

        $_SESSION = $this->closeSession();
        ignore_user_abort(true);

        $id = (int) $request['sw_id'];
        $this->updateCacheSimulationData($id);
        $sql = $this->getListSQL();
        $sql = "SELECT * FROM({$sql}) AS foo WHERE sw_id={$id}";
        $data = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        // manual number format
        $data['swc_estimated_cost_tot'] = $data['swc_estimated_cost_tot'] == '' ? '' : number_format($data['swc_estimated_cost_tot'], 0, ',', '.');
        $data['swc_expected_energy_saving_tot'] = $data['swc_expected_energy_saving_tot'] == '' ? '' : number_format($data['swc_expected_energy_saving_tot'], 0, ',', '.');
        $data['swc_expected_renewable_energy_production_tot'] = $data['swc_expected_renewable_energy_production_tot'] == '' ? '' : number_format($data['swc_expected_renewable_energy_production_tot'], 0, ',', '.');
        $data['swc_expected_co2_reduction_tot'] = $data['swc_expected_co2_reduction_tot'] == '' ? '' : number_format($data['swc_expected_co2_reduction_tot'], 0, ',', '.');

        return array('status' => R3_AJAX_NO_ERROR, 'response' => $data);
    }

    public function getSummaryTable($request) {
        $html = R3EcoGisSimulationHelper::getSummaryTableHTML(explodeInt($request['ac_id_list']), explodeInt($request['ac_perc_list']), $request['ss_type'], $request['ss_table'], $request['ss_category']);
        return array('status' => R3_AJAX_NO_ERROR,
            'html' => $html);
    }

    public function askDelSimulation($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = $request['id'];
        $name = $db->query("SELECT sw_title_$lang FROM simulation_work WHERE sw_id={$id}")->fetchColumn();
        if ($this->tryDeleteData($id)) {
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf(_("Sei sicuro di voler cancellare la simulazione \"%s\"?"), $name));
        } else {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _('Impossibile cancellare questa simulazione poichè vi sono dei dati ad essa collegati'));
        }
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}

