<?php

class eco_meter extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'energy_meter';

    /**
     * ecogis.energy_meter fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'em_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'em_serial', 'type' => 'text', 'required' => true, 'label' => _('Matricola (POD)')),
            array('name' => 'em_descr_1', 'type' => 'text', 'label' => _('Descrizione (Lingua 1)')),
            array('name' => 'em_descr_2', 'type' => 'text', 'label' => _('Descrizione (Lingua 2)')),
            array('name' => 'emo_id', 'type' => 'lookup', 'required' => true, 'lookup' => array('table' => 'energy_meter_object')),
            array('name' => 'em_object_id', 'type' => 'lookup', 'required' => true, 'lookup' => array('table' => 'building', 'field' => 'bu_id')),
            array('name' => 'esu_id', 'type' => 'lookup', 'required' => false, 'label' => _('Alimentazione'), 'lookup' => array('table' => 'energy_source_udm')),
            array('name' => 'up_id', 'type' => 'lookup', 'required' => false, 'label' => _('Fornitore'), 'lookup' => array('table' => 'utility_product')),
            array('name' => 'em_is_production', 'type' => 'boolean', 'label' => _('Produzione'), 'default' => 'F'),
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        $this->fields = $this->defFields();

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->do_id = $_SESSION['do_id'];

        $this->id = initVar('id');
        $this->act = initVar('act', 'list');
        $this->kind = initVar('kind');
        $this->bu_id = initVar('bu_id');
        $this->tabMode = initVar('tab_mode');

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('fetchUDM');
        $this->registerAjaxFunction('getEnergySourceList');
        $this->registerAjaxFunction('getUtilityProductList');
        $this->registerAjaxFunction('confirmDeleteMeter');
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
            $vlu['bu_id'] = $this->bu_id;
            $vlu['us_id'] = null;
            $vlu['es_id'] = null;
            $vlu['is_producer'] = 'F';
            $vlu['em_is_production'] = 'F';
            $vlu['devices'] = 0;
            $vlu['consumptions'] = 0;
        } else {
            $q = $db->createSelectQuery();
            $q->select('*')
                    ->from('energy_meter_data')
                    ->where('em_id=' . (int) $id);
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $this->bu_id = $vlu['bu_id'] = $vlu['em_object_id'];
            $vlu['devices'] = $db->query('SELECT COUNT(*) FROM device WHERE em_id=' . (int) $id)->fetchColumn();
            $vlu['consumptions'] = $db->query('SELECT COUNT(*) FROM consumption WHERE em_id=' . (int) $id)->fetchColumn();
            if ($vlu['us_id'] > 0) {
                $vlu['es_id'] = null;
            }
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('energy_meter', $vlu['em_id']));
        }
        $data = R3EcoGisHelper::getBuildingData($vlu['bu_id']);
        $this->mu_id = $data['mu_id'];
        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Return the data for a single customer
     */
    public function getLookupData() {
        $lkp = array();
        $db = ezcDbInstance::get();

        $constraint = array('esu_allow_in_building IS TRUE',
            $this->data['em_is_production'] == 'T' ? "esu_is_production IS TRUE" : "esu_is_consumption IS TRUE");
        $lkp['em_production_values'] = array('F' => _('Consumo'), 'T' => _('Produzione'));
        $lkp['us_values'] = R3EcogisHelper::getUtilitySupplierList($this->do_id, $this->mu_id, $this->kind);
        $lkp['es_values'] = R3EcoGisHelper::getEnergySourceList($this->do_id, $this->kind, array('constraints' => $constraint));
        if ($this->data['us_id'] > 0) {
            $lkp['up_values'] = R3EcoGisHelper::getUtilityProductList($this->do_id, $this->data['us_id'], $this->kind);
        }
        if (isset($lkp['es_values']) && count($lkp['es_values']) == 1 || $this->data['es_id'] > 0) {
            $lkp['udm_values'] = R3EcogisHelper::getEnergyUDMListByEnergySource($this->do_id, $this->kind, $this->act == 'add' ? key($lkp['es_values']) : $this->data['es_id']);
        }
        return $lkp;
    }

    public function getPageVars() {
        return array('bu_id' => $this->bu_id,
            'kind' => $this->kind,
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

        $data = R3EcoGisHelper::getBuildingData($this->bu_id);
        $this->mu_id = $data['mu_id'];

        $request = array_merge(array('us_id' => ''), $request);
        $request['em_id'] = forceInteger($request['id'], 0, false, '.');
        if ($this->act <> 'del') {
            // Change required fields
            if ($request['em_is_production'] == 'T') {
                $request['up_id'] = null;
                $this->setFieldAttrib('esu_id', array('required' => true));
            } else {
                if ($request['us_id'] == '') {
                    $this->setFieldAttrib('esu_id', array('required' => true));
                } else {
                    $this->setFieldAttrib('up_id', array('required' => true));
                }
            }
            if ($request['us_id'] == '') {
                $request['esu_id'] = R3EcoGisHelper::getEnergySourceUdmID($this->do_id, $request['es_id'], $request['udm_id'], $this->mu_id);
            } else {
                $request['esu_id'] = '';
            }
            $request['emo_id'] = R3EcoGisHelper::getEnergyMeterObjectIdByCode('BUILDING');
            $request['em_object_id'] = $request['bu_id'];
            $errors = $this->checkFormData($request);
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $id = $this->applyData($request);
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneMeter($id)");
        }
    }

    /**
     * Return the Energy source list (different values from consumption/production)
     * @param array $request    the request
     * @return array            the result data
     */
    public function getEnergySourceList($request) {
        $constraint = array('esu_allow_in_building IS TRUE',
            $request['em_is_production'] == 'T' ? "esu_is_production='T'" : "esu_is_consumption='T'");
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(R3EcoGisHelper::getEnergySourceList($this->do_id, $this->kind, array('constraints' => $constraint, 'allow_empty' => true))));
    }

    /**
     * Return the Energy source list (different values from consumption/production)
     * @param array $request    the request
     * @return array            the result data
     */
    public function getUtilityProductList($request) {
        $request = array_merge(array('us_id' => null), $request);
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(R3EcoGisHelper::getUtilityProductList($this->do_id, $request['us_id'], $this->kind)));
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function fetchUDM($request) {
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(R3EcogisHelper::getEnergyUDMListByEnergySource($this->do_id, $request['kind'], $request['es_id'], array('constraints' => 'esu_allow_in_building IS TRUE'))));
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function confirmDeleteMeter($request) {
        $db = ezcDbInstance::get();
        $id = forceInteger($request['id'], 0, false, '.');
        $name = $db->query("SELECT em_serial FROM energy_meter WHERE em_id={$id}")->fetchColumn();
        $devices = $db->query("SELECT COUNT(*) FROM device WHERE em_id={$id}")->fetchColumn();
        $consumptions = $db->query("SELECT COUNT(*) FROM consumption WHERE em_id={$id}")->fetchColumn();

        if ($devices == 0 && $consumptions == 0) {
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf(_('Sei sicuro di voler cancellare il contatore "%s"?'), $name));
        } else {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _('Impossibile cancellare il contatore poichè vi sono degli impianti e/o dei consumi adesso legati'));
        }
    }

    public function checkPerm() {
        $db = ezcDbInstance::get();

        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        // Extra security
        R3Security::checkEnergyMeterForBuilding($this->act, $this->bu_id, $this->id, array('method' => $this->method, 'skip_methods' => array('fetchUDM', 'getEnergySourceList', 'getUtilityProductList')));
    }

}
