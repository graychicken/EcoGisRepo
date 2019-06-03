<?php

class R3EcoGisGlobalConsumptionHelper {

    /**
     * Restituisce l'elenco delle fonti per l'editing
     */
    public function getEnergySourceList($do_id, $kind, array $opt = array()) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $defaultOptions = array('order' => 'gest_order, ges_order, ges_name, ges.ges_id, es_order, es_name, es.es_id, udm_order, udm_name, udm.udm_id');
        $opt = array_merge($defaultOptions, $opt);

        $sql = "SELECT ges.ges_id, ges_name_$lang AS ges_name, es.es_id, es_name_$lang AS es_name, udm.udm_id,
                       udm.udm_name_$lang AS udm_name, esu_id, esu_kwh_factor, esu_co2_factor
                FROM global_energy_source ges
                INNER JOIN global_energy_source_type gest ON ges.ges_id=gest.ges_id
                INNER JOIN global_type gt ON gt.gt_id=gest.gt_id AND gt_code=" . $db->quote($kind) . "
                INNER JOIN energy_source_udm esu ON ges.ges_id=esu.ges_id
                INNER JOIN energy_source es ON esu.es_id=es.es_id
                INNER JOIN udm on esu.udm_id=udm.udm_id
                WHERE esu.do_id IS NOT NULL
                ORDER BY esu.mu_id DESC, {$opt['order']}";

        $result = array();
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $result[$row['ges_id']]['name'] = $row['ges_name'];
            $result[$row['ges_id']]['source'][$row['es_id']]['name'] = $row['es_name'];
            $result[$row['ges_id']]['source'][$row['es_id']]['udm'][$row['udm_id']] = array('name' => $row['udm_name'],
                'id' => $row['esu_id'],
                'kwh_factor' => $row['esu_kwh_factor'],
                'co2_factor' => $row['esu_co2_factor']);
        }
        return $result;
    }

    public function getGlobalMethodList($do_id) {
        $do_id = (int) $do_id;
        return R3Opt::getOptList('global_method', 'gm_id', 'gm_name_' . R3Locale::getLanguageID(), array('order' => 'gm_order, gm_name_' . R3Locale::getLanguageID(),
                    'constraints' => array("(do_id IS NULL OR do_id={$do_id}) AND gm_visible IS TRUE")));
    }

}

class eco_global_consumption_row extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'global_subcategory';

    /**
     * ecogis.global_subcategory fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'gs_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'ge_id', 'type' => 'integer', 'required' => true),
            array('name' => 'gc_id', 'type' => 'integer', 'required' => true),
            array('name' => 'gs_name_1', 'type' => 'text', 'required' => true, 'label' => _('Nome')),
            array('name' => 'gs_name_2', 'type' => 'text', 'label' => _('Nome')),
            array('name' => 'gs_descr_1', 'type' => 'text', 'label' => _('Descrizione')),
            array('name' => 'gs_descr_2', 'type' => 'text', 'label' => _('Descrizione')),
            array('name' => 'gs_tot_value', 'type' => 'float', 'label' => _('Totale CO2')),
            array('name' => 'gs_tot_production_value', 'type' => 'float', 'label' => _('Produzione locale')),
            array('name' => 'gs_tot_emission_value', 'type' => 'float', 'label' => _('Emissioni di CO2')),
            array('name' => 'gs_tot_emission_factor', 'type' => 'float', 'label' => _('Fattori di emissione di CO2')),
            array('name' => 'gs_order', 'type' => 'integer', 'default' => '0'),
            array('name' => 'gm_id', 'type' => 'lookup', 'label' => _('Fonte dei dati'), 'lookup' => array('table' => 'global_method')),
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $this->id = initVar('id');
        $this->act = initVar('act', 'list');
        $this->tab_mode = initVar('tab_mode');
        $this->kind = initVar('kind');
        $this->ge_id = (int) initVar('ge_id');
        $this->gc_id = (int) initVar('gc_id');
        $this->limit = 0;
        $this->do_id = PageVar('do_id', $_SESSION['do_id'], false, false, $this->baseName);

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->fields = $this->defFields();
        $this->registerAjaxFunction('fetchUDM');
        $this->registerAjaxFunction('performEnergySourceRowCalc');
        $this->registerAjaxFunction('confirmDeleteGlobalConsumptionRow');
        $this->registerAjaxFunction('getEnergySource');
        $this->registerAjaxFunction('getEnergyUDM');
        $this->registerAjaxFunction('performActionCatalogCalc');
        $this->registerAjaxFunction('submitFormData');
    }

    public function getPageTitle() {
        
    }

    public function getListSQL() {
        
    }

    public function createListTableHeader(&$order) {
        
    }

    /**
     * Return the data for a single customer 
     */
    public function getData($id = null) {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        if ($this->act == 'add') {
            $sql = "SELECT gc_id, gc_name_$lang AS gc_name, CASE WHEN gc_total_only IS TRUE then 'T' ELSE 'F' END AS gc_total_only
                    FROM global_category
                    WHERE gc_id=" . (int) $this->gc_id;
            $vlu = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        } else {
            $sql = "SELECT gc.gc_id, gc.gc_name_$lang AS gc_name, CASE WHEN gc_total_only IS TRUE then 'T' ELSE 'F' END AS gc_total_only, gs.*
                    FROM global_subcategory gs
                    INNER JOIN global_category gc ON gs.gc_id=gc.gc_id
                    WHERE gs_id=" . (int) $this->id;
            $vlu = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
            $vlu['gs_tot_value'] = $vlu['gs_tot_value'] / 1000;
            $vlu['gs_tot_production_value'] = ($vlu['gs_tot_production_value'] == '' ? '' : $vlu['gs_tot_production_value'] / 1000);
            $vlu['gs_tot_emission_value'] = ($vlu['gs_tot_emission_value'] == '' ? '' : $vlu['gs_tot_emission_value'] / 1000);
            $vlu['gs_tot_emission_factor'] = ($vlu['gs_tot_emission_factor'] == '' ? '' : $vlu['gs_tot_emission_factor'] / 1000);
            $sql = "SELECT gd.ges_id, co_value, co_value*esu_kwh_factor AS co_value_kwh, co_value*esu_co2_factor AS co_value_co2,
                           esu.es_id, esu.udm_id, co_production_co2_factor
                    FROM consumption co
                    INNER JOIN energy_meter em ON co.em_id=em.em_id
                    INNER JOIN ecogis.energy_meter_object emo ON em.emo_id = emo.emo_id AND emo.emo_code::text = 'GLOBAL_ENERGY'::text
                    INNER JOIN ecogis.energy_source_udm esu ON em.esu_id = esu.esu_id
                    INNER JOIN global_data gd ON gd.gd_id=em.em_object_id
                    WHERE gs_id=" . (int) $this->id;
            foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
                $vlu['consumption'][$row['ges_id']] = $row;
                $vlu['consumption'][$row['ges_id']]['co_value'] = R3NumberFormat($vlu['consumption'][$row['ges_id']]['co_value'], 2, true);
                $vlu['consumption'][$row['ges_id']]['co_value_kwh'] = R3NumberFormat($vlu['consumption'][$row['ges_id']]['co_value_kwh'] / 1000, 2, true);
                if ($row['co_production_co2_factor'] === null) {
                    $vlu['consumption'][$row['ges_id']]['co_value_co2'] = R3NumberFormat($vlu['consumption'][$row['ges_id']]['co_value_co2'] / 1000, 2, true);
                } else {
                    $vlu['consumption'][$row['ges_id']]['co_value_co2'] = R3NumberFormat($row['co_value_kwh'] * $row['co_production_co2_factor'] / 1000, 2, true);
                }
                $vlu['consumption'][$row['ges_id']]['co_production_co2_factor'] = R3NumberFormat($vlu['consumption'][$row['ges_id']]['co_production_co2_factor'], null, true);
            }
        }

        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Return the data for a single customer 
     */
    public function getLookupData($id = null) {
        $lkp = array();
        $lkp['energy_source_list'] = R3EcoGisGlobalConsumptionHelper::getEnergySourceList($_SESSION['do_id'], $this->kind);
        $lkp['global_method_list'] = R3EcoGisGlobalConsumptionHelper::getGlobalMethodList($_SESSION['do_id']);
        return $lkp;
    }

    public function getPageVars() {
        return array('ge_id' => $this->ge_id,
            'gc_id' => $this->gc_id,
            'kind' => $this->kind);
    }

    public function getJSFiles() {
        return $this->includeJS(array($this->baseName . '.js',
                    'mapopenfunc.js'), $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        $mapres = explode('x', $this->auth->getConfigValue('SETTINGS', 'MAP_RES', '1024x768'));
        return array(
            'UserMapWidth' => $mapres[0],
            'UserMapHeight' => $mapres[1],
            'PopupErrorMsg' => _('ATTENZIONE!\n\nBlocco dei popup attivo. Impossibile aprire la mappa. Disabilitare il blocco dei popup del browser e riprovare'),
            'MapFileName' => '../map/index.php',
            'MapName' => 'ECOGIS',
            'askDeleteCurrentRow' => _('Eliminare la seguente riga?'));
    }

    // Restituisce l'elenco dei contatori associato ad un record di sotto categoria (global_source). 
    //   Serve per eliminare consumi e contatori finti (anche per modifica)
    protected function getEnergyMeterList($gs_id) {
        $db = ezcDbInstance::get();
        $sql = "SELECT em_id
                FROM energy_meter em
                INNER JOIN ecogis.global_data gd ON em.em_object_id=gd.gd_id
                INNER JOIN ecogis.energy_meter_object emo ON em.emo_id = emo.emo_id AND emo.emo_code::text = 'GLOBAL_ENERGY'::text
                WHERE gs_id=$gs_id";
        return $db->query($sql)->FetchAll(PDO::FETCH_COLUMN);
    }

    // Restituisce la fonte per il patto dei sindaci in base a fonte e udm
    protected function getGlobalEnergySource($es_id, $udm_id) {
        $db = ezcDbInstance::get();

        $es_id = (int) $es_id;
        $udm_id = (int) $udm_id;
        $sql = "SELECT ges_id FROM energy_source_udm WHERE es_id={$es_id} AND udm_id={$udm_id} AND do_id IS NULL";
        return $db->query($sql)->fetchColumn();
    }

    public function checkFormData(array &$formData, $kind) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $errors = parent::checkFormData($formData);
        $totalOnly = $db->query("SELECT gc_total_only FROM global_category WHERE gc_id=" . (int) $formData['gc_id'])->fetchColumn() == true;

        if (!$totalOnly && !in_array($kind, array('ENERGY_PRODUCTION', 'HEATH_PRODUCTION'))) {
            $tot = 0;
            $selectedEnergySources = array();

            foreach ($formData['ges_id_consumption'] as $key => $value) {
                if (empty($formData['ac_expected_energy_saving'][$key]))
                    continue;
                if (in_array($value, $selectedEnergySources)) {
                    $energySources = R3EcoGisGlobalConsumptionHelper::getEnergySourceList($_SESSION['do_id'], $this->kind);
                    $errors['popup_ges_id_' . $key] = array('CUSTOM_ERROR' => _('La fonte ' . $energySources[$value]['name'] . ' è già stata selezionata'));
                    continue;
                }
                $tot++;
                if (forceFloat($formData['ac_expected_energy_saving'][$key], null, '.') == null) {
                    $errors["popup_co_value_{$key}"] = array('CUSTOM_ERROR' => _("Valore non valido" . $formData['ac_expected_energy_saving'][$key]));
                } else {
                    if (empty($formData['es_id_consumption'][$key]) || empty($formData['udm_id_consumption'][$key])) {
                        $errors["popup_co_value_{$key}"] = array('CUSTOM_ERROR' => _("Indicare un'alimentazione"));
                    }
                }
                array_push($selectedEnergySources, $value);
            }

            if ($tot == 0) {
                $errors["popup_gs_name"] = array('CUSTOM_ERROR' => _("Indicare almeno un consumo"));
            }
        }
        return $errors;
    }

    // 
    public function submitFormData($request) {
        $errors = array();
        $db = ezcDbInstance::get();
        setLocale(LC_ALL, 'C');
        setLangInfo(array('thousands_sep' => "."));

        $request['gs_id'] = forceInteger($request['id'], 0, false, '.');
        if ($this->act == 'mod') {
            $sql = "SELECT ge_id, gc_id FROM global_subcategory WHERE gs_id={$request['gs_id']}";
            list($request['ge_id'], $request['gc_id']) = $db->query($sql)->fetch(PDO::FETCH_NUM);
        }
        if ($this->act <> 'del') {
            $errors = $this->checkFormData($request, $request['kind']);
        }

        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $db->beginTransaction();
            // Remove consumption
            $em_list = $this->getEnergyMeterList($request['gs_id']);
            if (count($em_list) > 0) {
                $sql = "DELETE FROM consumption WHERE em_id IN (" . implode(', ', $em_list) . ")";  // Remove consumption
                $db->exec($sql);
                $sql = "DELETE FROM energy_meter WHERE em_id IN (" . implode(', ', $em_list) . ")";  // Remove energy_meter
                $db->exec($sql);
            }

            $sql = "DELETE FROM global_data WHERE gs_id=" . $request['gs_id'];
            $db->exec($sql);
            if ($this->act == 'del') {
                $sql = "DELETE FROM global_subcategory WHERE gs_id=" . $request['gs_id'];
                $db->exec($sql);
            } else {
                if (isset($request['gs_tot_value'])) {
                    $request['gs_tot_value'] = $request['gs_tot_value'] * 1000;
                }
                if (isset($request['gs_tot_production_value'])) {
                    $request['gs_tot_production_value'] = $request['gs_tot_production_value'] * 1000;
                }
                if (isset($request['gs_tot_emission_value'])) {
                    $request['gs_tot_emission_value'] = $request['gs_tot_emission_value'] * 1000;
                }
                if (isset($request['gs_tot_emission_factor'])) {
                    $request['gs_tot_emission_factor'] = $request['gs_tot_emission_factor'] * 1000;
                }
                $id = $this->applyData($request);
                $geData = $db->query("SELECT mu_id, ge_year FROM global_entry WHERE ge_id={$request['ge_id']}")->fetch(PDO::FETCH_ASSOC);
                $year = $geData['ge_year'];
                $mu_id = $geData['mu_id'];

                $totalOnly = $db->query("SELECT gc_total_only FROM global_category WHERE gc_id=" . (int) $request['gc_id'])->fetchColumn() == true;
                if (!$totalOnly) {
                    foreach ($request['ges_id_consumption'] as $key => $value) {
                        if (empty($request['ac_expected_energy_saving'][$key]))
                            continue;
                        $ges_id = $this->getGlobalEnergySource($request['es_id_consumption'][$key], $request['udm_id_consumption'][$key]);
                        if (empty($ges_id)) {
                            throw new Exception("getGlobalEnergySource(" . $request['es_id_consumption'][$key] . ', ' . $request['udm_id_consumption'][$key] . ") key=$key faild");
                        }

                        $sql = "INSERT INTO global_data (ges_id, gs_id) VALUES ($ges_id, $id)";
                        $db->exec($sql);
                        $gd_id = $db->lastInsertId('global_data_gd_id_seq');
                        $esu_id = R3EcoGisHelper::getEnergySourceUdmID($this->do_id, $request['es_id_consumption'][$key], $request['udm_id_consumption'][$key], $mu_id, false);
                        if (!empty($esu_id)) {
                            $em_id = R3EcoGisHelper::addDummyEnergyMeter(array('esu_id' => $esu_id, 'em_object_id' => $gd_id), 'GLOBAL_ENERGY');
                            $data = array();
                            $data['co_start_date'] = "'{$year}-01-01'";
                            $data['co_end_date'] = "'{$year}-12-31'";
                            $data['co_value'] = forceFloat($request['ac_expected_energy_saving'][$key], null, '.');
                            $data['co_bill'] = 0;
                            $data['em_id'] = $em_id;
                            if (in_array($this->kind, array('ENERGY_PRODUCTION', 'HEATH_PRODUCTION'))) {
                                $data['co_production_co2_factor'] = (float) forceFloat($request['co_production_co2_factor'][$key], null, '.');
                            }
                            $sql = "INSERT INTO consumption (" . implode(', ', array_keys($data)) . ") VALUES (" . implode(', ', $data) . ")";
                            $db->exec($sql);
                        } else {
                            throw new Exception("Unknown esu [es_id={$request['es_id_consumption'][$key]}; udm_id={$request['udm_id_consumption'][$key]}; do_id={$this->do_id}; mu_id={$mu_id}]");
                        }
                    }
                }
                if (isset($request['geometryStatus']) && strtoupper($request['geometryStatus']) == 'CHANGED') {
                    $session_id = session_id();
                    $sql = "UPDATE global_subcategory
                            SET the_geom=foo.the_geom
                            FROM (SELECT ST_Multi(ST_Force_2d(ST_union(ST_Buffer(the_geom, 0.0)))) AS the_geom FROM edit_tmp_polygon WHERE session_id='{$session_id}') AS foo
                            WHERE gs_id=$id";
                    $db->exec($sql);
                }
            }
            $db->commit();
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneEnergySourceRow($id, '$this->kind')");
        }
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function fetchUDM($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        if ($request['es_id'] == '') {
            return array('status' => R3_AJAX_NO_ERROR,
                'data' => array('' => '--Selezionare--'));
        }
        $sql = "SELECT udm.udm_id, udm_name_1
                FROM energy_source_udm esu
                INNER JOIN udm ON esu.udm_id=udm.udm_id
                WHERE esu.es_id=" . (int) $request['es_id'] . " AND do_id IS NULL";
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => $db->query($sql)->FetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE));
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function performEnergySourceRowCalc($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $request = R3Locale::convert2PHP($request, true);
        $result = array();
        foreach (R3EcoGisGlobalConsumptionHelper::getEnergySourceList($_SESSION['do_id'], $this->kind) as $key => $value) {
            if (!isset($request["udm_id_{$key}"]) &&
                    isset($value['source'][$request["es_id_{$key}"]]['udm']) &&
                    count($value['source'][$request["es_id_{$key}"]]['udm']) == 1) {
                $request["udm_id_{$key}"] = key($value['source'][$request["es_id_{$key}"]]['udm']);
            }
            if (isset($request["es_id_{$key}"]) && $request["es_id_{$key}"] <> '' &&
                    isset($request["udm_id_{$key}"]) && $request["udm_id_{$key}"] <> '' &&
                    isset($request["co_value_{$key}"]) && $request["co_value_{$key}"] <> '') {
                $result["popup_co_{$key}_kwh"] = R3NumberFormat(forceFloat($request["co_value_{$key}"], null, '.') * $value['source'][$request["es_id_{$key}"]]['udm'][$request["udm_id_{$key}"]]['kwh_factor'], 0, true);
                $result["popup_co_{$key}_co2"] = R3NumberFormat(forceFloat($request["co_value_{$key}"], null, '.') * $value['source'][$request["es_id_{$key}"]]['udm'][$request["udm_id_{$key}"]]['co2_factor'], 0, true);
            } else {
                $result["popup_co_{$key}_co2"] = null;
                $result["popup_co_{$key}_kwh"] = null;
            }
        }
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => Null2Str($result));
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function confirmDeleteGlobalConsumptionRow($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = forceInteger($request['id'], 0, false, '.');
        $name = $db->query("SELECT gs_name_$lang FROM global_subcategory WHERE gs_id={$id}")->fetchColumn();
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => sprintf(_('Sei sicuro di voler cancellare la riga "%s"?'), $name));
    }

    public function checkPerm() {
        $db = ezcDbInstance::get();
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }

        if ($this->act == 'add') {
            R3Security::checkGlobalEntry($this->ge_id);
        } else {
            // Can edit/delete the given id
            if (!in_array($this->method, array('getEnergySource', 'getEnergyUDM'))) {
                R3Security::checkGlobalSubcategory($this->id);
            }
        }
    }

    public function getEnergySource($request) {
        $data = R3EcoGisGlobalConsumptionHelper::getEnergySourceList($_SESSION['do_id'], $request['type'], array('order' => 'ges_name, gest_order, ges_order, ges.ges_id, es_order, es_name, es.es_id, udm_order, udm_name, udm.udm_id'));
        $result = array();
        if (isset($data[$request['ges_id']]['source'])) {
            foreach ($data[$request['ges_id']]['source'] as $key => $val) {
                $result[$key] = $val['name'];
            }
        }
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(
                    $result
        ));
    }

    public function getEnergyUDM($request) {
        $data = R3EcoGisGlobalConsumptionHelper::getEnergySourceList($_SESSION['do_id'], $request['type'], array('order' => 'ges_name, gest_order, ges_order, ges.ges_id, es_order, es_name, es.es_id, udm_order, udm_name, udm.udm_id'));

        $result = array();
        if (isset($data[$request['ges_id']]['source'][$request['es_id']]['udm'])) {
            foreach ($data[$request['ges_id']]['source'][$request['es_id']]['udm'] as $key => $val) {
                $result[$key] = $val['name'];
            }
        }

        $result = R3Opt::addChooseItem($result, array('allow_empty' => count($result) <> 1));

        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(
                    $result
        ));
    }

    public function performActionCatalogCalc($request) {

        $energySavingData = R3EcoGisHelper::getEnergySourceUdmData($_SESSION['do_id'], $request['es_id_consumption'], $request['udm_id_consumption'], $request['mu_id']);
        $energyProductionData = null;

        $ac_expected_co2_reduction_total = 0;
        $ac_expected_co2_reduction = array();
        $ac_expected_energy_saving_mwh = array();
        foreach ($energySavingData as $i => $val) {
            $request['ac_expected_energy_saving'][$i] = forceFloat($request['ac_expected_energy_saving'][$i], null, '.');
            if (isset($request['co_production_co2_factor'])) {
                $ac_expected_co2_reduction_total += ($request['ac_expected_energy_saving'][$i] == '' ? null : ($request['ac_expected_energy_saving'][$i] * forceFloat($request['co_production_co2_factor'][$i], null, '.')));
                $ac_expected_co2_reduction[$i] = R3NumberFormat($request['ac_expected_energy_saving'][$i] == '' ? null : ($request['ac_expected_energy_saving'][$i] * forceFloat($request['co_production_co2_factor'][$i], null, '.')), null, true);
            } else {
                $ac_expected_co2_reduction_total += ($request['ac_expected_energy_saving'][$i] == '' ? null : ($request['ac_expected_energy_saving'][$i] * $energySavingData[$i]['esu_co2_factor'] / 1000));
                $ac_expected_co2_reduction[$i] = R3NumberFormat($request['ac_expected_energy_saving'][$i] == '' ? null : ($request['ac_expected_energy_saving'][$i] * $energySavingData[$i]['esu_co2_factor'] / 1000), null, true);
            }
            $ac_expected_energy_saving_mwh[$i] = R3NumberFormat($request['ac_expected_energy_saving'][$i] == '' ? null : ($request['ac_expected_energy_saving'][$i] * $energySavingData[$i]['esu_kwh_factor'] / 1000), null, true);
        }

        $energyProductionMWh = null;
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => array('ac_expected_co2_reduction_total' => R3NumberFormat($ac_expected_co2_reduction_total, null, true),
                'ac_expected_co2_reduction' => $ac_expected_co2_reduction,
                'ac_expected_energy_saving_mwh' => $ac_expected_energy_saving_mwh,
                'ac_expected_renewable_energy_production_mwh' => R3NumberFormat($energyProductionMWh, null, true),
            )
        );
    }

}
