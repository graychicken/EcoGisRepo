<?php

class eco_consumption extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'consumption';

    /**
     * ecogis.consumption fields definition
     */
    protected function defFields($isProduction) {
        $fields = array(
            array('name' => 'co_id', 'type' => 'integer', 'label' => _('PK'), 'is_primary_key' => true),
            array('name' => 'co_start_date', 'type' => 'date', 'required' => true, 'label' => _('Inizio')),
            array('name' => 'co_end_date', 'type' => 'date', 'required' => true, 'label' => _('Fine')),
            array('name' => 'co_value', 'type' => 'float', 'required' => true, 'label' => $isProduction ? _('Produzione') : _('Consumo')),
            array('name' => 'co_bill', 'type' => 'float', 'precision' => 2, 'required' => false, 'label' => $isProduction ? _('Ricavo') : _('Spesa')),
            array('name' => 'co_bill_is_calculated', 'type' => 'boolean', 'required' => true, 'default' => 'F'),
            array('name' => 'em_id', 'type' => 'integer', 'required' => true),
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->id = initVar('id');
        $this->act = initVar('act', 'list');
        $this->kind = PageVar('kind');
        $this->em_id = PageVar('em_id', null, $init | $reset, false, $this->baseName);
        $this->sl_id = PageVar('sl_id', null, $init | $reset, false, $this->baseName);
        $this->tabMode = PageVar('tab_mode', null, $init | $reset, false, $this->baseName);


        $data = R3EcoGisHelper::getMeterData($_SESSION['do_id'], $this->em_id);
        $this->fields = $this->defFields($data['em_is_production'] == 'T');

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('confirm_delete_consumption');
        $this->registerAjaxFunction('submitFormData');
    }

    public function getPageTitle() {
        
    }

    public function getListSQL() {
        
    }

    public function createListTableHeader(&$order) {
        
    }

    public function getElectricityUDMData($do_id) {
        $db = ezcDbInstance::get();
        $esuData = R3EcoGisHelper::getEnergySourceAndUdm($do_id, 'ELECTRICITY');
        $sql = "SELECT * " .
                "FROM ecogis.energy_source es " .
                "INNER JOIN ecogis.energy_type et ON es.et_id = et.et_id " .
                "INNER JOIN ecogis.energy_source_udm esu ON es.es_id = esu.es_id " .
                "INNER JOIN ecogis.udm ON esu.udm_id = udm.udm_id " .
                "WHERE esu.es_id={$esuData['es_id']} AND 
                      esu.udm_id={$esuData['udm_id']} AND                    
                      et_code='ELECTRICITY' AND 
                      esu_is_private IS FALSE AND 
                      esu_is_consumption IS TRUE 
                      AND esu.do_id IS NULL";
        return $db->query($sql)->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Return the data for a single customer
     */
    public function getData($id = null) {

        $lang = R3Locale::getLanguageID();
        if ($id === null) {
            $id = $this->id;
        }
        $db = ezcDbInstance::get();
        if ($this->act == 'add') {
            $vlu = array();
            if ($this->kind == 'street_lighting') {
                $data = $this->getElectricityUDMData($_SESSION['do_id']);
                $vlu['em_data']['udm_name'] = $data["udm_name_{$lang}"];
            } else {
                $vlu['em_data'] = R3EcoGisHelper::getMeterData($_SESSION['do_id'], $this->em_id);
            }
        } else {
            $q = $db->createSelectQuery();
            $q->select('*')
                    ->from('consumption_data')
                    ->where('co_id=' . (int) $id);
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $this->em_id = $vlu['em_id'];
            $vlu['em_data'] = R3EcoGisHelper::getMeterData($_SESSION['do_id'], $vlu['em_id']);
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('consumption', $vlu['co_id']));
            //em_is_production
        }
        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Return the data for a single customer
     */
    public function getLookupData() {
        $lkp = array();
        $lkp['insert_type_values'] = array('free' => _('Bolletta singola'),
            'month' => _('Bollette mensile'),
            'year' => _('Bollette annuale'));
        $lkp['insert_year_values'] = R3EcoGisHelper::getConsumptionYearList($_SESSION['do_id'], $this->kind);
        return $lkp;
    }

    public function getPageVars() {
        return array('kind' => $this->kind,
            'tab_mode' => $this->tabMode,
            'em_id' => $this->em_id, // Serve per consumo edificio (contatore)
            'sl_id' => $this->sl_id, // Serve per consuno tratto: Aggiunta contatore automatica
            'months' => range(1, 12)
        );
    }

    public function getJSFiles() {
        if (defined('R3_SINGLE_JS') && R3_SINGLE_JS === true) {
            return array();
        }
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        return array('date_format' => R3Locale::getJQueryDateFormat(),
            'date_separator' => R3Locale::getDateSeparator());
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $request = array_merge(array('insert_type' => 'free', 'reinsert' => ''), $request);
        $db = ezcDbInstance::get();
        $data = array();
        $errors = array();
        if ($request['kind'] == 'street_lighting' && $this->act == 'add') {
            // Valore finto per bypassare controlli automatici
            $request['em_id'] = '-1';
        }
        $checkDate = false;
        if ($this->act <> 'del') {
            switch ($request['insert_type']) {
                case 'free':
                    $checkDate = true;
                    $data[] = array('act' => $request['act'],
                        'co_start_date' => forceISODate($request['co_start_date_free']),
                        'co_end_date' => forceISODate($request['co_end_date_free']),
                        'co_value' => forceFloat($request['co_value_free'], null, '.'),
                        'co_bill' => forceFloat($request['co_bill_free'], null, '.'),
                        'co_bill_is_calculated' => 'F',
                        'em_id' => $request['em_id'],
                        'co_id' => forceInteger($request['id'], 0, false, '.'));
                    break;
                case 'month':
                    for ($i = 0; $i < 12; $i++) {
                        if ($request['co_start_date_month'][$i] <> '' &&
                                $request['co_end_date_month'][$i] <> '' &&
                                $request['co_value_month'][$i] <> '' &&
                                $request['co_bill_month'][$i] <> '') {
                            $data[] = array('act' => $request['act'],
                                'co_start_date' => forceISODate($request['co_start_date_month'][$i]),
                                'co_end_date' => forceISODate($request['co_end_date_month'][$i]),
                                'co_value' => forceFloat($request['co_value_month'][$i], null, '.'),
                                'co_bill' => forceFloat($request['co_bill_month'][$i], null, '.'),
                                'co_bill_is_calculated' => 'F',
                                'em_id' => $request['em_id']);
                        }
                    }
                    if (count($data) == 0) {
                        $errors['dummy'] = array('CUSTOM_ERROR' => _('Impossibile salvare. Nessun dato valido inserito'));
                    }
                    break;
                case 'year':
                    $data[] = array('act' => $request['act'],
                        'co_start_date' => forceISODate($request['co_start_date_year']),
                        'co_end_date' => forceISODate($request['co_end_date_year']),
                        'co_value' => forceFloat($request['co_value_year'], null, '.'),
                        'co_bill' => forceFloat($request['co_bill_year'], null, '.'),
                        'co_bill_is_calculated' => 'F',
                        'em_id' => $request['em_id']);
                    break;
            }
            foreach ($data as $values) {
                $errors = $this->checkFormData($values, $errors);
            }
            if ($checkDate) {
                // Verifica data inserimento per bollette singole
                if (forceISODate($request['co_start_date_free']) > forceISODate($request['co_end_date_free'])) {
                    $errors['co_end_date_bis'] = array('CUSTOM_ERROR' => _("La data di fine periodo precedenta a quella di inizio"));
                }
            }
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            if ($this->act == 'del') {
                $request['co_id'] = forceInteger($request['id'], 0, false, '.');
                if ($request['kind'] == 'street_lighting') {
                    // Ricavo contatore finto per cancellazione
                    $db->beginTransaction();
                    $em_id = $db->query("SELECT em_id FROM consumption WHERE co_id={$request['co_id']}")->fetchColumn();
                    $id = $this->applyData($request);
                    if ($db->query("SELECT COUNT(*) FROM consumption WHERE em_id={$em_id}")->fetchColumn() == 0) {
                        $db->exec("DELETE FROM energy_meter WHERE em_id={$em_id}");
                    }
                    $db->commit();
                } else {
                    $id = $this->applyData($request);
                }
            } else {
                $db->beginTransaction();
                if ($request['kind'] == 'street_lighting' && $this->act == 'add') {
                    $em_id = R3EcoGisHelper::getDummyEnergyMeter('STREET_LIGHTING', $request['sl_id']);
                    if ($em_id == '') {
                        // Aggiungo contatore finto per tratto stradale
                        $electricityData = $this->getElectricityUDMData($_SESSION['do_id']);
                        $em_id = R3EcoGisHelper::addDummyEnergyMeter(array('esu_id' => $electricityData['esu_id'], 'em_object_id' => $request['sl_id']), 'STREET_LIGHTING');
                    }
                    $data[0]['em_id'] = $em_id;
                }
                $id = array();
                foreach ($data as $values) {
                    $id[] = $this->applyData($values);
                }
                $db->commit();
            }
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneConsumption(" . json_encode($id) . ", '{$request['reinsert']}')");
        }
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function confirm_delete_consumption($request) {
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => sprintf(_("Sei sicuro di voler cancellare il consumo selezionato?")));
    }

    public function checkPerm() {
        $db = ezcDbInstance::get();

        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        // Extra security
        $id = $this->kind == 'street_lighting' ? $this->sl_id : $this->em_id;
        R3Security::checkConsumptionForEnergyMeter($this->act, $id, $this->id, array('kind' => $this->kind));
    }

}
