<?php

class eco_device extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'device';

    /**
     * ecogis.device fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'dev_id', 'type' => 'integer', 'label' => _('dev_id'), 'is_primary_key' => true),
            array('name' => 'dt_id', 'type' => 'lookup', 'required' => true, 'label' => _('Tipo impianto'), 'lookup' => array('table' => 'device_type')),
            array('name' => 'dt_extradata_1', 'type' => 'text', 'label' => _('Tipo impianto')),
            array('name' => 'dt_extradata_2', 'type' => 'text', 'label' => _('Tipo impianto')),
            array('name' => 'dev_power', 'type' => 'float', 'label' => _('Potenza')),
            array('name' => 'dev_connection', 'type' => 'integer', 'label' => _('Numero utenze')),
            array('name' => 'dev_energy_service', 'type' => 'boolean', 'label' => _('Servizio energia'), 'default' => false),
            array('name' => 'em_id', 'type' => 'lookup', 'required' => true, 'label' => _('Contatore'), 'lookup' => array('table' => 'energy_meter')),
            array('name' => 'dev_serial', 'type' => 'text', 'label' => _('Numero impianto')),
            array('name' => 'dev_install_date', 'type' => 'date', 'label' => _('Data installazione')),
            array('name' => 'dev_end_date', 'type' => 'date', 'label' => _('Data fine esercizio')),
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        $this->fields = $this->defFields();

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->id = initVar('id');
        $this->act = initVar('act', 'list');
        $this->kind = PageVar('kind');
        $this->em_id = PageVar('em_id', null, $init | $reset, false, $this->baseName);
        $this->tabMode = PageVar('tab_mode', null, $init | $reset, false, $this->baseName);
        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('confirm_delete_device');
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
        if ($id === null) {
            $id = $this->id;
        }
        $db = ezcDbInstance::get();
        if ($this->act == 'add') {
            $vlu = array();
            $vlu['em_data'] = R3EcoGisHelper::getMeterData($_SESSION['do_id'], $this->em_id);
        } else {
            $q = $db->createSelectQuery();
            $q->select('*')
                    ->from('device_data')
                    ->where('dev_id=' . (int) $id);
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $this->em_id = $vlu['em_id'];
            $vlu['em_data'] = R3EcoGisHelper::getMeterData($_SESSION['do_id'], $vlu['em_id']);
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('device', $vlu['dev_id']));
        }
        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Return the data for a single customer
     */
    public function getLookupData() {
        $lkp = array();
        $energyMeterData = $this->data['em_data'];
        $lkp['dt_values'] = R3EcoGisHelper::getDeviceTypeList($_SESSION['do_id'], $this->kind, array('production' => $energyMeterData['em_is_production'] == 'T' ? true : null,
                    'consumption' => $energyMeterData['em_is_production'] == 'T' ? null : true));
        if (1 == 2) { // Inizio e fine impianto sono solo anni
            $lkp['dev_install_date_values'] = R3EcoGisHelper::getDeviceInstallYearList($_SESSION['do_id'], $this->kind);
            $lkp['dev_end_date_values'] = R3EcoGisHelper::getDeviceEndYearList($_SESSION['do_id'], $this->kind);
        }
        return $lkp;
    }

    public function getPageVars() {
        return array('kind' => $this->kind,
            'em_id' => $this->em_id,
            'tab_mode' => $this->tabMode);
    }

    public function getJSFiles() {
        if (defined('R3_SINGLE_JS') && R3_SINGLE_JS === true) {
            return array();
        }
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();

        if ($this->act <> 'add') {
            // Security check
            // R3Security::checkDevice(@$request['id']);
        }
        $request['dev_id'] = $request['id'];
        if ($this->act <> 'del') {
            $errors = $this->checkFormData($request);
        } else {
            $request['dt_extradata'] = null;
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $db = ezcDbInstance::get();
            $id = $this->applyData($request);
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneDevice($id)");
        }
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function fetch_udm($request) {
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcogisHelper::getEnergyUDMListByEnergySource($_SESSION['do_id'], $request['kind'], $request['es_id']));
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function confirm_delete_device($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $name = $db->query("SELECT dev_name_$lang AS dev_name FROM device_data WHERE dev_id=" . (int) $request['id'])->fetchColumn();
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => sprintf(_("Sei sicuro di voler cancellare l'impianto \"%s\"?"), trim($name)));
    }

    public function checkPerm() {
        $db = ezcDbInstance::get();

        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        // Extra security
        R3Security::checkDeviceForEnergyMeter($this->act, $this->em_id, $this->id);
    }

}
