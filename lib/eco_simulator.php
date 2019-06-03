<?php

class R3Simulator {

    private $data = array();
    private $simData = array();
    private $globalSimData = array();  // come simData, ma diviso per categoria. Serve per creazione PAES
    private $log = array();

    public function __construct() {
        $this->resetData();
        $this->resetSimulationData();
        $this->resetLog();
    }

    public function resetData() {
        $defData = array('national_electricity_factor' => null,
                    'inventory' => array(
                        1 => array('present' => null,
                            'year' => null,
                            'population' => null,
                            'consumption' => array('electricity' => null),
                            'emission' => array('total' => null, 'per_capita' => null),
                            'production' => array('electricity' => null, 'production_emission' => null),
                            'green_energy' => array('consumption' => null, 'factor' => null, 'emission' => null)),
                        2 => array('present' => null,
                            'year' => null,
                            'population' => null,
                            'consumption' => array('electricity' => null),
                            'emission' => array('total' => null, 'per_capita' => null),
                            'production' => array('electricity' => null, 'production_emission' => null),
                            'green_energy' => array('consumption' => null, 'factor' => null, 'emission' => null)),
                    ),
                    'target' => array(
                        1 => array('present' => null,
                            'year' => null,
                            'population' => null),
                        2 => array('present' => null,
                            'year' => null,
                            'population' => null)
                    ),
                    'simulation' => array(
                        1 => array('present' => null,
                            'year' => null,
                            'population' => null),
                        2 => array('present' => null,
                            'year' => null,
                            'population' => null)
                    )
        );
        $this->data = array_merge($defData);
    }

    public function resetSimulationData() {
        $this->simData = array('detail' => array(), 'sum' => array(1 => array(), 2 => array()), 'efe' => array(1 => null, 2 => null));
        $this->globalSimData = array();
    }

    public function resetLog() {
        $this->log = array();
    }

    public function addLog($level, $text) {
        $this->log[] = array('level' => $level, 'text' => $text);
    }

    // Carico i dati dall'id della simulazione e dal db
    public function loadDataFromSimulation($sw_id) {
        $db = ezcDbInstance::get();

        $q = $db->createSelectQuery();
        $q->select("do_id, mu_id,
                    ge_id AS ge_id_1, ge_year AS ge_year_1, ge_citizen AS ge_citizen_1,
                    ge_id_2, ge_2_year AS ge_year_2, ge_2_citizen AS ge_citizen_2,
                    gst_reduction_target_year AS gst_reduction_target_year_1, 
                    gst_reduction_target_year_long_term AS gst_reduction_target_year_2,
                    gst_reduction_target_citizen AS gst_reduction_target_citizen_1, 
                    gst_reduction_target_citizen_long_term AS gst_reduction_target_citizen_2, 
                    gst_reduction_target AS gst_reduction_target_1,
                    gst_reduction_target_long_term AS gst_reduction_target_2")
                ->from('simulation_work_data')
                ->where($q->expr->eq('sw_id', $sw_id));
        //national_electricity_factor
        $data = $db->query($q)->fetch(PDO::FETCH_ASSOC);


        $this->data['national_electricity_factor'] = $this->loadNationalElectricityFactor($data['do_id']); //, $data['mu_id']);
        for ($i = 1; $i <= 2; $i++) {
            if ($data["ge_year_{$i}"] <> '') {
                $inventoryData = $this->loadEmissionDataFromInventory($data["ge_id_{$i}"]);
                $this->data['inventory'][$i]['present'] = true;
                $this->data['inventory'][$i]['year'] = $data["ge_year_{$i}"];
                $this->data['inventory'][$i]['population'] = $data["ge_citizen_{$i}"];
                $this->data['inventory'][$i]['consumption'] = $inventoryData['consumption'];
                $this->data['inventory'][$i]['emission'] = $inventoryData['emission'];
                $this->data['inventory'][$i]['production'] = $inventoryData['production'];
                $this->data['inventory'][$i]['green_energy'] = $inventoryData['green_energy'];
                if ($this->data['inventory'][$i]['population'] > 0) {
                    $this->data['inventory'][$i]['consumption']['per_capita'] = $this->data['inventory'][$i]['consumption']['electricity'] / $this->data['inventory'][$i]['population'];
                    $this->data['inventory'][$i]['emission']['per_capita'] = $this->data['inventory'][$i]['emission']['total'] / $this->data['inventory'][$i]['population'];
                } else {
                    $this->data['inventory'][$i]['consumption']['per_capita'] = null;
                    $this->data['inventory'][$i]['emission']['per_capita'] = null;
                }
            }

            if ($data["gst_reduction_target_year_{$i}"] <> '' &&
                    $data["gst_reduction_target_citizen_{$i}"] > 0 &&
                    $data["gst_reduction_target_{$i}"] <> '') {
                $this->data['target'][$i]['present'] = true;
                $this->data['target'][$i]['year'] = $data["gst_reduction_target_year_{$i}"];
                $this->data['target'][$i]['population'] = $data["gst_reduction_target_citizen_{$i}"];
                $this->data['target'][$i]['reduction_target'] = $data["gst_reduction_target_{$i}"];
            }
        }
    }

    public function loadNationalElectricityFactor($do_id) {
        $db = ezcDbInstance::get();

        $esData = R3EcoGisHelper::getEnergySourceAndUdm($do_id, 'ELECTRICITY');

        $sql = "SELECT esu_co2_factor
                FROM energy_source_udm
                WHERE es_id=? AND udm_id=? AND do_id=? AND esu_is_private IS FALSE AND mu_id IS NULL";
        $stmt = $db->prepare($sql);
        $stmt->execute(array($esData['es_id'], $esData['udm_id'], $_SESSION['do_id']));
        $electricityConversionFactor = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($electricityConversionFactor === false) {
            throw new Exception("Conversion factor for es_id={$esData['es_id']}, udm_id={$esData['udm_id']}, do_id={$_SESSION['do_id']} not found");
        }
        return $electricityConversionFactor['esu_co2_factor'];
    }

    // Load data from database
    public function loadEmissionDataFrominventory($ge_id) {
        $db = ezcDbInstance::get();
        R3EcoGisHelper::includeHelperClass('obj.global_result_table.php');

        // Emissioni
        $data = R3EcoGisGlobalTableHelper::getCategoriesData($ge_id, 'EMISSION', 1);
        $result['emission']['total'] = $data['table_sum'];

        // Consumi
        $consumptionData = R3EcoGisGlobalTableHelper::getCategoriesData($ge_id, 'CONSUMPTION', 1);


        // Ricavo ID PAES Elettricità (dovrebbe essere 1 nei db standard)
        $sql = "SELECT ges_id
                FROM global_energy_source ges
                INNER JOIN global_energy_type get ON ges.get_id=get.get_id WHERE get_code='ELECTRICITY'";
        $electricityGesId = $db->query($sql)->fetchColumn();
        if (!isset($data['sum']['source'][$electricityGesId])) {
            $result['consumption']['electricity'] = null;
        } else {
            $result['consumption']['electricity'] = isset($consumptionData['sum']['source'][$electricityGesId]) ? $consumptionData['sum']['source'][$electricityGesId] : 0;
        }
        $result['consumption']['total'] = $consumptionData['sum']['total'];

        // Produzione
        $productionData = R3EcoGisGlobalTableHelper::getCategoriesData($ge_id, 'ENERGY_PRODUCTION', 1);
        $result['production']['electricity'] = $productionData['production_sum']['tot'];
        $result['production']['production_emission'] = $productionData['production_emission_sum']['tot'];
        // Energia verde
        $sql = "SELECT ge_green_electricity_purchase, ge_green_electricity_co2_factor
                FROM global_entry
                WHERE ge_id=?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array($ge_id));
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $result['green_energy']['consumption'] = $data['ge_green_electricity_purchase'];
        $result['green_energy']['factor'] = $data['ge_green_electricity_co2_factor'];
        $result['green_energy']['emission'] = $result['green_energy']['consumption'] * $result['green_energy']['factor'];

        // Energia rinnovabile
        $sql = "SELECT ges.ges_id, ges_name_1 AS ges_name
FROM global_energy_source ges
INNER JOIN global_energy_type get ON ges.get_id=get.get_id
INNER JOIN global_energy_source_type gest ON ges.ges_id=gest.ges_id
INNER JOIN global_type gt ON gt.gt_id=gest.gt_id
WHERE get_code='RENEWABLE' AND gt_code='CONSUMPTION'
ORDER BY gest_order";
        $renewableConsumption = 0;
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $tot = 0;
            foreach ($consumptionData['data'] as $data) {
                if (isset($data['sum']['source'][$row['ges_id']])) {
                    $tot += $data['sum']['source'][$row['ges_id']];
                }
            }
            $gesIds[$row['ges_id']] = array('id' => $row['ges_id'], 'name' => $row['ges_name'], 'tot' => $tot);
            $renewableConsumption += $tot;
        }
        $result['green_energy']['production'] = $renewableConsumption;
        return $result;
    }

    public function loadSimulationData($ac_id, $perc) {
        static $stmt1 = null, $stmt2, $stmt3;

        if ($stmt1 === null) {
            $db = ezcDbInstance::get();
            $lang = R3Locale::getLanguageID();

            //Totale riduzione CO2 da azioni
            // Nome simulazione e totale energia prodotta
            $sql1 = "SELECT ac_name_{$lang} AS ac_name, es_name_{$lang} AS es_name, udm_name_{$lang} AS udm_name,
                 ac_estimated_cost, ac_estimated_public_financing, ac_estimated_other_financing, esu_co2_factor, esu_kwh_factor,
                 ac_co2_reduction,
                 ac_expected_renewable_energy_production, esu_id_production,
                 ac_green_electricity_purchase, ac_green_electricity_co2_factor, ac.gc_id
                 FROM action_catalog ac
                 LEFT JOIN energy_source_udm esu ON ac.esu_id_production=esu.esu_id
                 LEFT JOIN energy_source es ON esu.es_id=es.es_id
                 LEFT JOIN udm ON esu.udm_id=udm.udm_id
                 WHERE ac_id=?";
            // Energia risparmiata (più entry in più udm - possibile applicazione efe al posto del fattore standard)
            $sql2 = "SELECT et_code, es_name_{$lang} AS es_name, udm_name_{$lang} AS udm_name, ace_expected_energy_saving, esu_co2_factor, esu_kwh_factor
                FROM action_catalog_energy ace
                LEFT JOIN energy_source_udm esu ON ace.esu_id=esu.esu_id
                LEFT JOIN energy_source es ON esu.es_id=es.es_id
                LEFT JOIN energy_type et ON es.et_id=et.et_id
                LEFT JOIN udm ON esu.udm_id=udm.udm_id
                WHERE ace.ac_id=?";

            $sql3 = "SELECT acby_year, acby_benefit
                FROM action_catalog_benefit_year WHERE ac_id=? AND acby_year<=2020
                ORDER BY acby_year DESC
                LIMIT 1";
            $stmt1 = $db->prepare($sql1);
            $stmt2 = $db->prepare($sql2);
            $stmt3 = $db->prepare($sql3);
        }
        $stmt1->execute(array($ac_id));
        $acData = $stmt1->fetch(PDO::FETCH_ASSOC);
        $stmt2->execute(array($ac_id));
        $stmt3->execute(array($ac_id));
        $acDataBenefit = $stmt3->fetch(PDO::FETCH_ASSOC);
        $gc_id = $acData['gc_id'];

        // Inizializzazione variabili globali
        $hasElectricity = false;
        $hasEnergySaving = false;

        $actionGoodness = $perc / 100;  // Bontà azione
        if ($acDataBenefit === false) {
            $benefit = 1;  // Beneficio
        } else {
            $benefit = $acDataBenefit['acby_benefit'] / 100;  // beneficio
        }
        $logText = sprintf('Simulazione %s ', $acData['ac_name']) .
                ($actionGoodness <> 1 ? sprintf('[Efficacia: %s%%] ', 100 * $actionGoodness) : '') .
                ($acDataBenefit !== false ? sprintf('[Beneficio: %s%% al %s] ', 100 * $benefit, $acDataBenefit['acby_year']) : '');

        self::addLog(2, $logText);

        $result = array('estimated_cost' => null,
            'electricity_saving_kwh' => null,
            'energy_saving_kwh' => null,
            'energy_saving_co2' => null,
            'energy_production_kwh' => null,
            'energy_purchase_kwh' => null,
            'energy_purchase_factor' => null,
            'energy_purchase_co2' => null,
            'co2_reduction' => null);

        $globalResult = array();
        $globalResult[$gc_id] = $result;

        if ($acData['ac_estimated_cost'] == '') {
            $acData['ac_estimated_cost'] = 0;
        }
        if (!isset($acData['ac_estimated_public_financing']) || $acData['ac_estimated_public_financing'] == '') {
            $acData['ac_estimated_public_financing'] = 0;
        }
        if (!isset($acData['ac_estimated_other_financing']) || $acData['ac_estimated_other_financing'] == '') {
            $acData['ac_estimated_other_financing'] = 0;
        }



        // Costi totali stimati
        if ($acData['ac_estimated_cost'] <> '0' || $acData['ac_estimated_public_financing'] <> '0' || $acData['ac_estimated_other_financing'] <> '0') {
            $cost = $acData['ac_estimated_cost'] - $acData['ac_estimated_public_financing'] - $acData['ac_estimated_other_financing'];
            //$totals['estimated_cost'] += $cost;  // Non applico efficacia e benficio ai costi
            $result['estimated_cost'] = $cost;
            $globalResult[$gc_id]['estimated_cost'] = $cost;
            self::addLog(3, sprintf(_('Costo: €%.2f'), $cost));
        }

        while ($acDataEnergy = $stmt2->fetch(PDO::FETCH_ASSOC)) {
            if ($acDataEnergy['ace_expected_energy_saving'] == '') {
                $acDataEnergy['ace_expected_energy_saving'] = 0;
            }
            //echo "[{$acDataEnergy['ace_expected_energy_saving']}]";
            if ($acDataEnergy['ace_expected_energy_saving'] <> '0') {
                $energySavingKWh = $acDataEnergy['ace_expected_energy_saving'] * $acDataEnergy['esu_kwh_factor'];
                $energySavingkgCO2 = $acDataEnergy['ace_expected_energy_saving'] * $acDataEnergy['esu_co2_factor'];
                $logText = sprintf('Risparmio energetico previsto: %.2f%s di %s (=%.2fMWh)', $acDataEnergy['ace_expected_energy_saving'], $acDataEnergy['udm_name'], $acDataEnergy['es_name'], $energySavingKWh / 1000);
                if ($actionGoodness <> 1) {
                    $energySavingKWh = $energySavingKWh * $actionGoodness;
                    $energySavingkgCO2 = $energySavingkgCO2 * $actionGoodness;
                    $logText .= sprintf('; Applicazione efficacia: %.2fMWh', $energySavingKWh / 1000);
                }
                if ($benefit <> 1) {
                    $energySavingKWh = $energySavingKWh * $benefit;
                    $energySavingkgCO2 = $energySavingkgCO2 * $benefit;
                    $logText .= sprintf('; Applicazione beneficio: %.2fMWh', $energySavingKWh / 1000);
                }
                if ($acDataEnergy['et_code'] == 'ELECTRICITY') {
                    //$totals['electricity_saving_kwh'] += $energySavingKWh;
                    $result['electricity_saving_kwh'] += $energySavingKWh;
                    $globalResult[$gc_id]['electricity_saving_kwh'] += $energySavingKWh;
                    $hasElectricity = true;
                } else {
                    $logText .= sprintf('; CO2: %.2f tCO2 ', $energySavingkgCO2 / 1000);
                    //$totals['energy_saving_kwh'] += $energySavingKWh;
                    //$totals['energy_saving_co2'] += $energySavingkgCO2;
                    $result['energy_saving_kwh'] += $energySavingKWh;
                    $result['energy_saving_co2'] += $energySavingkgCO2;
                    $globalResult[$gc_id]['energy_saving_kwh'] += $energySavingKWh;
                    $globalResult[$gc_id]['energy_saving_co2'] += $energySavingkgCO2;

                    $hasEnergySaving = true;
                }
                self::addLog(3, $logText);
            }
        }

        if (isset($acData['ac_expected_renewable_energy_production']) && $acData['ac_expected_renewable_energy_production'] <> '') {
            $productionKWh = $acData['ac_expected_renewable_energy_production'] * $acData['esu_kwh_factor'];
            $logText = sprintf('Produzione prevista: %.2f%s di %s (=%.2fMWh) ', $acData['ac_expected_renewable_energy_production'], $acData['udm_name'], $acData['es_name'], $productionKWh / 1000);
            if ($actionGoodness <> 1) {
                $productionKWh = $productionKWh * $actionGoodness;
                $logText .= sprintf('Applicazione efficacia: %.2fMWh ', $productionKWh / 1000);
            }
            if ($benefit <> 1) {
                $productionKWh = $productionKWh * $benefit;
                $logText .= sprintf('Applicazione beneficio: %.2fMWh ', $productionKWh / 1000);
            }
            $result['energy_production_kwh'] = $productionKWh;
            $globalResult[$gc_id]['energy_production_kwh'] = $productionKWh;
            self::addLog(3, $logText);
        }
        if (isset($acData['ac_green_electricity_purchase']) && $acData['ac_green_electricity_purchase'] <> '' && $acData['ac_green_electricity_purchase'] <> '0') {
            $purchaseKWh = $acData['ac_green_electricity_purchase'];
            $logText = sprintf('Acquisti energia verde: %.2fMWh ', $purchaseKWh / 1000);
            if ($actionGoodness <> 1) {
                $purchaseKWh = $purchaseKWh * $actionGoodness;
                $logText .= sprintf('Applicazione efficacia: %.2fMWh ', $purchaseKWh / 1000);
            }
            if ($benefit <> 1) {
                $purchaseKWh = $purchaseKWh * $benefit;
                $logText .= sprintf('Applicazione beneficio: %.2fMWh ', $purchaseKWh / 1000);
            }
            $result['energy_purchase_kwh'] = $purchaseKWh;
            $result['energy_purchase_factor'] = $acData['ac_green_electricity_co2_factor'];
            $result['energy_purchase_co2'] = $purchaseKWh * $acData['ac_green_electricity_co2_factor'];

            $globalResult[$gc_id]['energy_purchase_kwh'] = $purchaseKWh;
            $globalResult[$gc_id]['energy_purchase_factor'] = $acData['ac_green_electricity_co2_factor'];
            $globalResult[$gc_id]['energy_purchase_co2'] = $purchaseKWh * $acData['ac_green_electricity_co2_factor'];

            self::addLog(3, $logText);
        }

        if (isset($acData['ac_co2_reduction']) && $acData['ac_co2_reduction'] <> '' && $acData['ac_co2_reduction'] <> '0') {
            $co2FixedReduction = $acData['ac_co2_reduction'];
            $logText = sprintf('Riduzione fissa di CO2: %.2ftCO2 ', $co2FixedReduction / 1000);
            if ($actionGoodness <> 1) {
                $co2FixedReduction = $co2FixedReduction * $actionGoodness;
                $logText .= sprintf('Applicazione efficacia: %.2ftCO2 ', $co2FixedReduction / 1000);
            }
            if ($benefit <> 1) {
                $co2FixedReduction = $co2FixedReduction * $benefit;
                $logText .= sprintf('Applicazione beneficio: %.2ftCO2 ', $co2FixedReduction / 1000);
            }
            $result['co2_reduction'] = $co2FixedReduction;

            $globalResult[$gc_id]['co2_reduction'] = $co2FixedReduction;

            self::addLog(3, $logText);
        }

        $this->simData['detail'][$ac_id] = $result;
        $this->globalSimData['detail'][$gc_id][$ac_id] = $result;
    }

    public function sumData() {
        $tot = array('estimated_cost' => null,
            'electricity_saving_kwh' => null,
            'energy_saving_kwh' => null,
            'energy_saving_co2' => null,
            'energy_production_kwh' => null,
            'energy_purchase_kwh' => null,
            'energy_purchase_factor' => null,
            'energy_purchase_co2' => null,
            'co2_reduction' => null);
        foreach ($this->simData['detail'] as $data) {
            foreach ($tot as $key => $dummy) {
                $tot[$key] += $data[$key];
            }
        }
        return array(1 => $tot, 2 => $tot);
    }

    public function calculateEFE() {
        if (!$this->data['inventory'][1]['present']) {
            // manca inventario. Impossibile calcolare l'efe
            throw new Exception("Can't calculate EFE in eco_simulatio::calculateEFE: Missing inventory 1");
            //return array(1=>null, 2=>null);
        }
        $efe = array();
        $tce = array();
        $lpe = array();
        $gep = array();
        $co2lpe = array();
        $co2gep = array();
        for ($i = 1; $i <= 2; $i++) {
            $targetYear = $this->data['target'][$i]['year'];
            self::addLog(1, _("Calcolo EFE {$targetYear}"));
            if ($this->data['inventory'][2]['present']) {
                $tce[$i] = ($this->data['target'][$i]['population'] *
                        $this->data['inventory'][2]['consumption']['per_capita']) - $this->simData['sum'][$i]['electricity_saving_kwh'];
                $lpe[$i] = $this->data['inventory'][2]['production']['electricity'] + $this->simData['sum'][$i]['energy_production_kwh'];
                $gep[$i] = $this->data['inventory'][2]['green_energy']['consumption'];
                $co2lpe[$i] = $this->data['inventory'][2]['production']['production_emission'];
                $co2gep[$i] = $this->data['inventory'][2]['green_energy']['emission'] + ($this->simData['sum'][$i]['energy_purchase_kwh'] * $this->simData['sum'][$i]['energy_purchase_factor']);

                $tceTitle = "(Popolazione {$targetYear} * Consumo elettrico {$this->data['inventory'][2]['year']} procapite) - Elettricità risparmiata da simulazioni [({$this->data['target'][$i]['population']} * {$this->data['inventory'][2]['consumption']['per_capita']} kWh) - {$this->simData['sum'][$i]['electricity_saving_kwh']} kWh]";
                $lpeTitle = "Produzione elettrica {$this->data['inventory'][2]['year']} + Produzione elettrica da simulazioni [{$this->data['inventory'][2]['production']['electricity']} kWh + {$this->simData['sum'][$i]['energy_production_kwh']} kWh]";
                $gepTitle = "Acquisti elettricità verde {$this->data['inventory'][2]['year']} [{$this->data['inventory'][2]['green_energy']['consumption']} kWh]";
                $neefeTitle = "Fattore emissione nazionale [{$this->data['national_electricity_factor']}] [kg/kWh";
                $co2lpeTitle = "Emissioni da produzione elettrica locale {$this->data['inventory'][2]['year']} [{$this->data['inventory'][2]['production']['production_emission']} kg]";
                $co2gepTitle = "Emissioni da energia elettrica verde {$this->data['inventory'][2]['year']} [{$this->data['inventory'][2]['green_energy']['emission']} kg + ({$this->simData['sum'][$i]['energy_purchase_kwh']} kWh * {$this->simData['sum'][$i]['energy_purchase_factor']} kg/kWh)]";

                self::addLog(2, sprintf(_("TCE: %s MWh <img src='../images/ico_info_micro.gif' title='{$tceTitle}' />"), round($tce[$i] / 1000, 2)));
                self::addLog(2, sprintf(_("LPE: %s MWh <img src='../images/ico_info_micro.gif' title='{$lpeTitle}' />"), round($lpe[$i] / 1000, 2)));
                self::addLog(2, sprintf(_("GEP: %s MWh <img src='../images/ico_info_micro.gif' title='{$gepTitle}' />"), round($gep[$i] / 1000, 2)));
                self::addLog(2, sprintf(_("NEEFE: %s t/MWh <img src='../images/ico_info_micro.gif' title='{$neefeTitle}' />"), $this->data['national_electricity_factor']));
                self::addLog(2, sprintf(_("CO2LPE: %s t <img src='../images/ico_info_micro.gif' title='{$co2lpeTitle}' />"), round($co2lpe[$i] / 1000, 2)));
                self::addLog(2, sprintf(_("CO2GEP: %s t <img src='../images/ico_info_micro.gif' title='{$co2gepTitle}' />"), round($co2gep[$i] / 1000, 2)));
            } else {
                $tce[$i] = ($this->data['target'][$i]['population'] *
                        $this->data['inventory'][1]['consumption']['per_capita']) - $this->simData['sum'][$i]['electricity_saving_kwh'];
                $lpe[$i] = $this->data['inventory'][1]['production']['electricity'] + $this->simData['sum'][$i]['energy_production_kwh'];
                $gep[$i] = $this->data['inventory'][1]['green_energy']['consumption'];
                $co2lpe[$i] = $this->data['inventory'][1]['production']['production_emission'];
                $co2gep[$i] = $this->data['inventory'][1]['green_energy']['emission'] + ($this->simData['sum'][$i]['energy_purchase_kwh'] * $this->simData['sum'][$i]['energy_purchase_factor']);
                if ($i == 1) {
                    $tceTitle = "(Popolazione {$targetYear} * Consumo elettrico {$this->data['inventory'][$i]['year']} procapite) - Elettricità risparmiata da simulazioni [({$this->data['target'][$i]['population']} * {$this->data['inventory'][1]['consumption']['per_capita']} kWh) - {$this->simData['sum'][$i]['electricity_saving_kwh']} kWh]";
                    $lpeTitle = "Produzione elettrica {$this->data['inventory'][1]['year']} + Produzione elettrica da simulazioni [{$this->data['inventory'][1]['production']['electricity']} kWh + {$this->simData['sum'][$i]['energy_production_kwh']} kWh]";
                    $gepTitle = "Acquisti elettricità verde {$this->data['inventory'][1]['year']} [{$this->data['inventory'][1]['green_energy']['consumption']} kWh]";
                    $neefeTitle = "Fattore emissione nazionale [{$this->data['national_electricity_factor']}] [kg/kWh";
                    $co2lpeTitle = "Emissioni da produzione elettrica locale {$this->data['inventory'][1]['year']} [{$this->data['inventory'][1]['production']['production_emission']} kg]";
                    $co2gepTitle = "Emissioni da energia elettrica verde {$this->data['inventory'][1]['year']} [{$this->data['inventory'][1]['green_energy']['emission']} kg + ({$this->simData['sum'][$i]['energy_purchase_kwh']} kWh * {$this->simData['sum'][$i]['energy_purchase_factor']} kg/kWh)]";

                    self::addLog(2, sprintf(_("TCE: %s MWh <img src='../images/ico_info_micro.gif' title='{$tceTitle}' />"), round($tce[$i] / 1000, 2)));
                    self::addLog(2, sprintf(_("LPE: %s MWh <img src='../images/ico_info_micro.gif' title='{$lpeTitle}' />"), round($lpe[$i] / 1000, 2)));
                    self::addLog(2, sprintf(_("GEP: %s MWh <img src='../images/ico_info_micro.gif' title='{$gepTitle}' />"), round($gep[$i] / 1000, 2)));
                    self::addLog(2, sprintf(_("NEEFE: %s t/MWh <img src='../images/ico_info_micro.gif' title='{$neefeTitle}' />"), $this->data['national_electricity_factor']));
                    self::addLog(2, sprintf(_("CO2LPE: %s t <img src='../images/ico_info_micro.gif' title='{$co2lpeTitle}' />"), round($co2lpe[$i] / 1000, 2)));
                    self::addLog(2, sprintf(_("CO2GEP: %s t <img src='../images/ico_info_micro.gif' title='{$co2gepTitle}' />"), round($co2gep[$i] / 1000, 2)));
                }
            }
            if ($tce[$i] == 0) {
                // Qui solo se ho un inventario non completo
                $efe[$i] = $this->data['national_electricity_factor'];
            } else {
                $efe[$i] = (($tce[$i] - $lpe[$i] - $gep[$i]) * $this->data['national_electricity_factor'] + $co2lpe[$i] + $co2gep[$i]) / $tce[$i];
            }
            $efeTitle = "EFE = [(TCE - LPE - GEP) * NEEFE + CO2LPE + CO2GEP] / (TCE)";
            self::addLog(2, sprintf(_("EFE[%s]: %s <img src='../images/ico_info_micro.gif' title='{$efeTitle}' />"), $targetYear, $efe[$i]));

            if ($efe[$i] < 0) {
                // Comune esportatore netto
                self::addLog(2, sprintf(_('EFE-EXPORT[%s]: %s [€]'), $targetYear, $efe[$i]));
                $efe[$i] = ($co2lpe[$i] + $co2gep[$i]) / ($lpe[$i] + $gep[$i]);
            }
        }
        return $efe;
    }

    public function calculate() {
        $lastInventory = $this->data['inventory'][2]['present'] ? 2 : 1;
        $lastInventoryYear = $this->data['inventory'][$lastInventory]['year'];
        $this->simData['sum'] = $this->sumData();
        if ($this->data['inventory'][1]['present']) {
            $this->simData['efe'] = $this->calculateEFE();
        }

        self::addLog(1, _('Totali delle azioni selezionate'));
        self::addLog(2, sprintf(_('Totale costo azioni: %.2f [€]'), $this->simData['sum'][1]['estimated_cost']));
        self::addLog(2, sprintf(_('Totale risparmio elettrico: %.2f [MWh]'), $this->simData['sum'][1]['electricity_saving_kwh'] / 1000));
        self::addLog(2, sprintf(_('Totale risparmio non elettrico: %.2f [MWh]'), $this->simData['sum'][1]['energy_saving_kwh'] / 1000));
        self::addLog(2, sprintf(_('Totale CO2 elettrica risparmiata: %.2f [t]'), '?'));
        self::addLog(2, sprintf(_('Totale CO2 non elettrica risparmiata: %.2f [t]'), $this->simData['sum'][1]['energy_saving_co2'] / 1000));
        self::addLog(2, sprintf(_('Totale produzione: %.2f [MWh]'), $this->simData['sum'][1]['energy_production_kwh'] / 1000));
        self::addLog(2, sprintf(_('Totale CO2 fissa ridotta: %.2f [t]'), $this->simData['sum'][1]['co2_reduction'] / 1000));

        $co2Ridotta = $this->simData['sum'][1]['co2_reduction'];

        for ($i = 1; $i <= 2; $i++) {
            $inventoryNo = min($i, $lastInventory);
            $targetYear = $this->data['target'][$i]['year'];
            self::addLog(1, _("Riepilogo simulazione {$targetYear}"));

            if ($this->data['inventory'][$lastInventory]['present'] == true && $this->data['target'][$i]['present'] == true) {
                $reduction = (100 - $this->data['target'][$i]['reduction_target']) / 100;
                self::addLog(2, sprintf(_('Riduzione: %.2f %% [pro capite / assoluta????]'), $this->data['target'][$i]['reduction_target']));

                $this->data['simulation'][$i]['present'] = $this->data['target'][$i]['present'];
                $this->data['simulation'][$i]['year'] = $this->data['target'][$i]['year'];
                $this->data['simulation'][$i]['population'] = $this->data['target'][$i]['population'];
                $this->data['simulation'][$i]['efe'] = $this->simData['efe'][$i];

                $this->data['simulation'][$i]['emission']['total'] = $this->data['inventory'][$lastInventory]['emission']['total'] / $this->data['inventory'][$lastInventory]['population'] * $this->data['simulation'][$i]['population'];
                $title = "Calcolate in base alla variazione di popolazione e all´ultimo inventario disponibile\nEmissioni {$lastInventoryYear} / Popolazione {$lastInventoryYear} * Popolazione {$targetYear} [{$this->data['inventory'][$lastInventory]['emission']['total']} kg / {$this->data['inventory'][$lastInventory]['population']} * {$this->data['simulation'][$i]['population']}]";
                self::addLog(2, sprintf(_("Emissioni totali assolute: %s t <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['emission']['total'] / 1000, 2)));

                $this->data['simulation'][$i]['emission']['per_capita'] = $this->data['simulation'][$i]['emission']['total'] / $this->data['simulation'][$i]['population'];
                $title = "Emissioni totali assolute {$targetYear} / Popolazione {$targetYear} [{$this->data['simulation'][$i]['emission']['total']} kg / {$this->data['simulation'][$i]['population']}]";
                self::addLog(2, sprintf(_("Emissioni totali pro capite: %s t/ab <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['emission']['per_capita'] / 1000, 2)));

                $this->data['simulation'][$i]['target_emission']['per_capita'] = $reduction * $this->data['inventory'][1]['emission']['per_capita'];
                $title = "Riduzione * emissioni pro capite {$this->data['inventory'][1]['year']} [{$reduction} * {$this->data['inventory'][1]['emission']['per_capita']} kg/ab]";
                self::addLog(2, sprintf(_("Target emissioni pro capite: %s t/ab <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['target_emission']['per_capita'] / 1000, 2)));

                $this->data['simulation'][$i]['target_emission']['total'] = $this->data['simulation'][$i]['target_emission']['per_capita'] * $this->data['simulation'][$i]['population'];
                $title = "Riduzione pro capite {$targetYear} * abitanti {$targetYear} [{$this->data['simulation'][$i]['target_emission']['per_capita']} kg/ab * {$this->data['simulation'][$i]['population']}]";
                self::addLog(2, sprintf(_("Target emissioni assolute: %s t <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['target_emission']['total'] / 1000, 2)));

                $this->data['simulation'][$i]['target']['total'] = $this->data['simulation'][$i]['emission']['total'] - $this->data['simulation'][$i]['target_emission']['total'];
                $title = "Emissioni totali {$targetYear} - target di riduzione {$targetYear} [{$this->data['simulation'][$i]['emission']['total']} kg - {$this->data['simulation'][$i]['target_emission']['total']}]";
                self::addLog(2, sprintf(_("Obiettivo di riduzione: %s t <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['target']['total'] / 1000, 2)));

                $this->data['simulation'][$i]['simulation_reduction']['total'] = ($this->data['simulation'][$i]['efe'] * $this->simData['sum'][$i]['electricity_saving_kwh']) +
                        $this->simData['sum'][$i]['energy_saving_co2'] +
                        ($this->simData['sum'][$i]['energy_production_kwh'] * $this->data['national_electricity_factor']) +
                        $co2Ridotta;

                $title = "(EFE {$this->data['target'][$i]['year']} * elettricità risparmiata) + Emissioni risparmiate + (Energia prodotta * NEEFE) + Emissioni ridotte [({$this->data['simulation'][$i]['efe']} * {$this->simData['sum'][$i]['electricity_saving_kwh']}) + {$this->simData['sum'][$i]['energy_saving_co2']} + ({$this->simData['sum'][$i]['energy_production_kwh']} * {$this->data['national_electricity_factor']}) + {$co2Ridotta}]";
                self::addLog(2, sprintf(_("Totale riduzione CO2 da azioni: %s t <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['simulation_reduction']['total'] / 1000, 2)));

                $this->data['simulation'][$i]['simulation_emission']['total'] = $this->data['simulation'][$i]['emission']['total'] - $this->data['simulation'][$i]['simulation_reduction']['total'];
                $title = "Emissioni totali assolute - emissioni da simulazione [{$this->data['simulation'][$i]['emission']['total']} - {$this->data['simulation'][$i]['simulation_reduction']['total']}]";
                self::addLog(2, sprintf(_("Emissioni totali da simulazione: %s t <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['simulation_emission']['total'] / 1000, 2)));

                $this->data['simulation'][$i]['simulation_emission']['per_capita'] = $this->data['simulation'][$i]['simulation_emission']['total'] / $this->data['simulation'][$i]['population'];
                $title = "Emissioni totali da simulazione / popolazione {$targetYear} [{$this->data['simulation'][$i]['simulation_emission']['total']} / {$this->data['simulation'][$i]['population']}]";
                self::addLog(2, sprintf(_("Emissioni pro capite da simulazione: %s t/ab <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['simulation_emission']['per_capita'] / 1000, 2)));

                $this->data['simulation'][$i]['paes_target']['total'] = -(($this->data['simulation'][$i]['simulation_emission']['total'] - $this->data['inventory'][1]['emission']['total']) / $this->data['inventory'][1]['emission']['total'] * 100);
                $title = "((Emissioni totali da simulazione - Emissioni totali {$this->data['inventory'][1]['year']}) / Emissioni totali {$this->data['inventory'][1]['year']} * 100 [-(({$this->data['simulation'][$i]['simulation_emission']['total']} - {$this->data['inventory'][1]['emission']['total']}) / {$this->data['inventory'][1]['emission']['total']} * 100)]";
                self::addLog(2, sprintf(_("Obietti PAES assoluto raggiunto: %s %% <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['paes_target']['total'], 2)));

                $this->data['simulation'][$i]['paes_target']['per_capita'] = -(($this->data['simulation'][$i]['simulation_emission']['per_capita'] - $this->data['inventory'][1]['emission']['per_capita']) / $this->data['inventory'][1]['emission']['per_capita'] * 100);
                $title = "((Emissioni pro capite da simulazione - Emissioni pro capite {$this->data['inventory'][1]['year']}) / Emissioni pro capite {$this->data['inventory'][1]['year']} * 100 [-(({$this->data['simulation'][$i]['simulation_emission']['per_capita']} - {$this->data['inventory'][1]['emission']['per_capita']}) / {$this->data['inventory'][1]['emission']['per_capita']} * 100)]";
                self::addLog(2, sprintf(_("Obietti PAES pro capite raggiunto: %s %% <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['paes_target']['per_capita'], 2)));

                $this->data['simulation'][$i]['renewal_production']['total'] = $this->data['inventory'][$lastInventory]['green_energy']['production'] + $this->simData['sum'][$i]['energy_production_kwh'];
                $title = "Energia verde acquistata {$this->data['inventory'][$lastInventory]['year']} + Energia prodotta da simulazioni [{$this->data['inventory'][$lastInventory]['green_energy']['production']} + {$this->simData['sum'][$i]['energy_production_kwh']} kWh]";
                self::addLog(2, sprintf(_("Energia da fonti rinnovabili: %s MWh <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['renewal_production']['total'] / 1000, 2)));

                $this->data['simulation'][$i]['renewal_production']['total_perc'] = $this->data['simulation'][$i]['renewal_production']['total'] / (($this->data['inventory'][$lastInventory]['consumption']['total'] / $this->data['inventory'][$lastInventory]['population'] * $this->data['simulation'][$i]['population']) -
                        ($this->simData['sum'][$i]['electricity_saving_kwh'] + $this->simData['sum'][1]['energy_saving_kwh'])) * 100;
                $title = "Produzione da rinnovabile {$targetYear} / (Consumo totale {$this->data['inventory'][$lastInventory]['year']} / Popolazione {$this->data['inventory'][$lastInventory]['year']} * Popolazione {$this->data['simulation'][$i]['year']}) " .
                        " - (Risparmio elettrico da simulazione + Risparmio non elettrico da simulazione)) * 100" .
                        " [{$this->data['simulation'][$i]['renewal_production']['total']} / (({$this->data['inventory'][$lastInventory]['consumption']['total']} / {$this->data['inventory'][$lastInventory]['population']} * {$this->data['simulation'][$i]['population']}) - ({$this->simData['sum'][$i]['electricity_saving_kwh']} - {$this->simData['sum'][1]['energy_saving_kwh']})) * 100]";
                self::addLog(2, sprintf(_("Energia da fonti rinnovabili: %s %% <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['renewal_production']['total_perc'], 2)));

                $consumptionSimulation = $this->data['inventory'][$lastInventory]['consumption']['total'] / $this->data['inventory'][1]['population'] * $this->data['simulation'][$i]['population'];

                $this->data['simulation'][$i]['energy_saving']['total'] = $this->simData['sum'][$i]['electricity_saving_kwh'] + $this->simData['sum'][$i]['energy_saving_kwh'];
                $title = "Energia elettrica risparmiata + Energia non elettrica risparmiata [{$this->simData['sum'][$i]['electricity_saving_kwh']} + {$this->simData['sum'][$i]['energy_saving_kwh']}]";
                self::addLog(2, sprintf(_("Risparmio energetico totale: %s MWh <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['energy_saving']['total'] / 1000, 2)));

                $this->data['simulation'][$i]['energy_saving']['total_perc'] = $this->data['simulation'][$i]['energy_saving']['total'] /
                        ($this->data['inventory'][1]['consumption']['total']) * 100;
                $title = "Risparmio energetico totale / Consumo totale {$this->data['inventory'][1]['year']} *100";
                self::addLog(2, sprintf(_("Risparmio energetico: %s %% <img src='../images/ico_info_micro.gif' title='{$title}' />"), round($this->data['simulation'][$i]['energy_saving']['total_perc'], 2)));

                $this->data['simulation'][$i]['cost']['total'] = $this->simData['sum'][$i]['estimated_cost'];
                $this->data['simulation'][$i]['cost']['per_capita'] = $this->simData['sum'][$i]['estimated_cost'] / $this->data['simulation'][$i]['population'];

                $this->data['simulation'][$i]['goal_reached']['total'] = $this->data['simulation'][$i]['paes_target']['total'] >= $this->data['target'][$i]['reduction_target'];
                $this->data['simulation'][$i]['goal_reached']['per_capita'] = $this->data['simulation'][$i]['paes_target']['per_capita'] >= $this->data['target'][$i]['reduction_target'];
            }
        }
    }

    public function getData($factor = 1, $returnAsLocale = false) {
        $lastInventory = $this->data['inventory'][2]['present'] ? 2 : 1;

        $data = $this->data;

        // Apply factor
        for ($i = 1; $i <= 2; $i++) {
            $inventoryNo = min($i, $lastInventory);
            if ($this->data['inventory'][$i]['present'] == true) {
                $data['inventory'][$i]['consumption']['electricity'] = $data['inventory'][$inventoryNo]['consumption']['electricity'] / $factor;
                $data['inventory'][$i]['consumption']['per_capita'] = $data['inventory'][$inventoryNo]['consumption']['per_capita'] / $factor;
                $data['inventory'][$i]['emission']['total'] = $data['inventory'][$inventoryNo]['emission']['total'] / $factor;
                $data['inventory'][$i]['emission']['per_capita'] = $data['inventory'][$inventoryNo]['emission']['per_capita'] / $factor;
                $data['inventory'][$i]['production']['electricity'] = $data['inventory'][$inventoryNo]['production']['electricity'] / $factor;
                $data['inventory'][$i]['production']['production_emission'] = $data['inventory'][$inventoryNo]['production']['production_emission'] / $factor;
                $data['inventory'][$i]['green_energy']['consumption'] = $data['inventory'][$inventoryNo]['green_energy']['consumption'] / $factor;
                $data['inventory'][$i]['green_energy']['emission'] = $data['inventory'][$inventoryNo]['green_energy']['emission'] / $factor;
                $data['inventory'][$i]['green_energy']['production'] = $data['inventory'][$inventoryNo]['green_energy']['production'] / $factor;
            }
            if (isset($data['simulation'][$i]['emission'])) {
                $data['simulation'][$i]['emission']['total'] = $data['simulation'][$i]['emission']['total'] / $factor;
                $data['simulation'][$i]['emission']['per_capita'] = $data['simulation'][$i]['emission']['per_capita'] / $factor;
                $data['simulation'][$i]['target_emission']['total'] = $data['simulation'][$i]['target_emission']['total'] / $factor;
                $data['simulation'][$i]['target_emission']['per_capita'] = $data['simulation'][$i]['target_emission']['per_capita'] / $factor;
                $data['simulation'][$i]['target']['total'] = $data['simulation'][$i]['target']['total'] / $factor;
                $data['simulation'][$i]['simulation_reduction']['total'] = $data['simulation'][$i]['simulation_reduction']['total'] / $factor;
                $data['simulation'][$i]['simulation_emission']['total'] = $data['simulation'][$i]['simulation_emission']['total'] / $factor;
                $data['simulation'][$i]['simulation_emission']['per_capita'] = $data['simulation'][$i]['simulation_emission']['per_capita'] / $factor;
                $data['simulation'][$i]['renewal_production']['total'] = $data['simulation'][$i]['renewal_production']['total'] / $factor;
            }
        }
        if ($returnAsLocale) {
            for ($i = 1; $i <= 2; $i++) {
                $inventoryNo = max($i, $lastInventory);

                if ($this->data['inventory'][$i]['present'] == true) {
                    $data['inventory'][$i]['population'] = R3NumberFormat($data['inventory'][$i]['population'], 0, true);
                    $data['inventory'][$i]['consumption']['electricity'] = R3NumberFormat($data['inventory'][$i]['consumption']['electricity'], 2, true);
                    $data['inventory'][$i]['consumption']['per_capita'] = R3NumberFormat($data['inventory'][$i]['consumption']['per_capita'], 2, true);
                    $data['inventory'][$i]['emission']['total'] = R3NumberFormat($data['inventory'][$i]['emission']['total'], 2, true);
                    $data['inventory'][$i]['emission']['per_capita'] = R3NumberFormat($data['inventory'][$i]['emission']['per_capita'], 2, true);
                    $data['inventory'][$i]['production']['electricity'] = R3NumberFormat($data['inventory'][$i]['production']['electricity'], 2, true);
                    $data['inventory'][$i]['production']['production_emission'] = R3NumberFormat($data['inventory'][$i]['production']['production_emission'], 2, true);
                    $data['inventory'][$i]['green_energy']['consumption'] = R3NumberFormat($data['inventory'][$i]['green_energy']['consumption'], 2, true);
                    $data['inventory'][$i]['green_energy']['emission'] = R3NumberFormat($data['inventory'][$i]['green_energy']['emission'], 2, true);
                    $data['inventory'][$i]['green_energy']['production'] = R3NumberFormat($data['inventory'][$i]['green_energy']['production'], 2, true);
                    $data['target'][$i]['population'] = R3NumberFormat($data['target'][$i]['population'], 0, true);
                }
                if (isset($data['simulation'][$i]['emission'])) {
                    $data['simulation'][$i]['population'] = R3NumberFormat($data['simulation'][$i]['population'], 0, true);
                    $data['simulation'][$i]['emission']['total'] = R3NumberFormat($data['simulation'][$i]['emission']['total'], 2, true);
                    $data['simulation'][$i]['emission']['per_capita'] = R3NumberFormat($data['simulation'][$i]['emission']['per_capita'], 2, true);
                    $data['simulation'][$i]['target_emission']['total'] = R3NumberFormat($data['simulation'][$i]['target_emission']['total'], 2, true);
                    $data['simulation'][$i]['target_emission']['per_capita'] = R3NumberFormat($data['simulation'][$i]['target_emission']['per_capita'], 2, true);
                    $data['simulation'][$i]['target']['total'] = R3NumberFormat($data['simulation'][$i]['target']['total'], 2, true);
                    $data['simulation'][$i]['simulation_reduction']['total'] = R3NumberFormat($data['simulation'][$i]['simulation_reduction']['total'], 2, true);
                    $data['simulation'][$i]['simulation_emission']['total'] = R3NumberFormat($data['simulation'][$i]['simulation_emission']['total'], 2, true);
                    $data['simulation'][$i]['simulation_emission']['per_capita'] = R3NumberFormat($data['simulation'][$i]['simulation_emission']['per_capita'], 2, true);
                    $data['simulation'][$i]['paes_target']['total'] = R3NumberFormat($data['simulation'][$i]['paes_target']['total'], 2, true);
                    $data['simulation'][$i]['paes_target']['per_capita'] = R3NumberFormat($data['simulation'][$i]['paes_target']['per_capita'], 2, true);
                    $data['simulation'][$i]['renewal_production']['total'] = R3NumberFormat($data['simulation'][$i]['renewal_production']['total'], 2, true);
                    $data['simulation'][$i]['renewal_production']['total_perc'] = R3NumberFormat($data['simulation'][$i]['renewal_production']['total_perc'], 2, true);
                    $data['simulation'][$i]['energy_saving']['total_perc'] = R3NumberFormat($data['simulation'][$i]['energy_saving']['total_perc'], 2, true);
                    $data['simulation'][$i]['cost']['total'] = R3NumberFormat($data['simulation'][$i]['cost']['total'], 2, true);
                    $data['simulation'][$i]['cost']['per_capita'] = R3NumberFormat($data['simulation'][$i]['cost']['per_capita'], 2, true);
                }
            }
        }
        return $data;
    }

    public function getSimulationData() {
        return $this->simData;
    }

    protected function getParentCategoryIdBycategoryId($categoryId) {
        $db = ezcDbInstance::get();
        $sql = "SELECT gc_parent_id FROM global_category WHERE gc_id=?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array($categoryId));
        return $stmt->fetchColumn();
    }

    // Sommo e raggruppo risparmi elettrici (applicando efe) con non elettrici
    protected function sumGlobalSimData() {

        $this->globalSimData['category_sum'] = array();
        $this->globalSimData['global_sum'] = array('estimated_cost' => null, 'energy_saving_kwh' => null, 'energy_production_kwh' => null, 'energy_saving_co2' => null);

        $efe = $this->simData['efe'][1];
        $detailData = $this->globalSimData['detail'];
        $this->globalSimData['detail'] = array();
        foreach ($detailData as $gc_id => $data1) {
            $gc_id_parent = self::getParentCategoryIdBycategoryId($gc_id);
            foreach ($data1 as $ac_id => $data) {
                $this->globalSimData['detail'][$gc_id][$ac_id] = array('estimated_cost' => $data['estimated_cost'],
                    'energy_saving_kwh' => ($data['electricity_saving_kwh'] + $data['energy_saving_kwh']),
                    'energy_production_kwh' => $data['energy_production_kwh'],
                    'energy_saving_co2' => ($data['electricity_saving_kwh'] * $efe + $data['energy_saving_co2'] + $data['energy_purchase_co2']));
                if (!isset($this->globalSimData['category_sum'][$gc_id_parent])) {
                    $this->globalSimData['category_sum'][$gc_id_parent] = array('estimated_cost' => null, 'energy_saving_kwh' => null, 'energy_production_kwh' => null, 'energy_saving_co2' => null);
                }
                $this->globalSimData['category_sum'][$gc_id_parent]['estimated_cost'] += $data['estimated_cost'];
                $this->globalSimData['category_sum'][$gc_id_parent]['energy_saving_kwh'] += ($data['electricity_saving_kwh'] + $data['energy_saving_kwh']);
                $this->globalSimData['category_sum'][$gc_id_parent]['energy_production_kwh'] += $data['energy_production_kwh'];
                $this->globalSimData['category_sum'][$gc_id_parent]['energy_saving_co2'] += ($data['electricity_saving_kwh'] * $efe + $data['energy_saving_co2'] + $data['energy_purchase_co2']);

                $this->globalSimData['global_sum']['estimated_cost'] += $data['estimated_cost'];
                $this->globalSimData['global_sum']['energy_saving_kwh'] += ($data['electricity_saving_kwh'] + $data['energy_saving_kwh']);
                $this->globalSimData['global_sum']['energy_production_kwh'] += $data['energy_production_kwh'];
                $this->globalSimData['global_sum']['energy_saving_co2'] += ($data['electricity_saving_kwh'] * $efe + $data['energy_saving_co2'] + $data['energy_purchase_co2']);
            }
        }
    }

    public function getGlobalSimulationData() {
        $this->sumGlobalSimData();
        return $this->globalSimData;
    }

    public function getLog() {
        return $this->log;
    }

    static public function invalidateAllSimulations($do_id = null) {
        $db = ezcDbInstance::get();
        $do_id = (int) $do_id;
        if ($do_id === null) {
            $sql = "UPDATE ecogis.simulation_work SET sw_invalid=sw_invalid+1";
        } else {
            $sql = "UPDATE ecogis.simulation_work SET sw_invalid=sw_invalid+1 WHERE mu_id IN (SELECT mu_id FROM ecogis.municipality WHERE do_id={$do_id})";
        }
        $db->exec($sql);
    }

}
