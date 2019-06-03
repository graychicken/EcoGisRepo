<?php

class eco_building_fr_st_cm extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Field prefix
     */
    protected $prefix;

    /**
     * Table
     */
    protected $table = null; // Chenged runtime

    protected function defFieldsFraction() {
        $fields = array(
            array('name' => 'fr_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'mu_id', 'type' => 'lookup', 'required' => true, 'label' => _('Comune'), 'lookup' => array('table' => 'municipality')),
            array('name' => 'fr_name_1', 'type' => 'text', 'size' => 80, 'required' => true, 'label' => _('Nome')),
            array('name' => 'fr_name_2', 'type' => 'text', 'size' => 80, 'required' => false, 'label' => _('Nome')),
        );
        return $fields;
    }

    protected function defFieldsStreet() {
        $fields = array(
            array('name' => 'st_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'mu_id', 'type' => 'lookup', 'required' => true, 'label' => _('Comune'), 'lookup' => array('table' => 'municipality')),
            array('name' => 'st_name_1', 'type' => 'text', 'size' => 80, 'required' => true, 'label' => _('Nome')),
            array('name' => 'st_name_2', 'type' => 'text', 'size' => 80, 'required' => false, 'label' => _('Nome')),
        );
        return $fields;
    }

    protected function defFieldsCatMunic() {
        $fields = array(
            array('name' => 'cm_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'mu_id', 'type' => 'lookup', 'required' => true, 'label' => _('Comune'), 'lookup' => array('table' => 'municipality')),
            array('name' => 'cm_name_1', 'type' => 'text', 'size' => 80, 'required' => true, 'label' => _('Nome')),
            array('name' => 'cm_name_2', 'type' => 'text', 'size' => 80, 'required' => false, 'label' => _('Nome')),
        );
        return $fields;
    }

    public function defFields() {
        switch ($this->kind) {
            case 'fraction':
                $this->lookupName = _('Frazione');
                $this->table = 'common.fraction';
                $this->fields = $this->defFieldsFraction();
                $this->prefix = 'fr';
                break;
            case 'street':
                $this->lookupName = _('Strada');
                $this->table = 'common.street';
                $this->fields = $this->defFieldsStreet();
                $this->prefix = 'st';
                break;
            case 'catmunic':
                $this->lookupName = _('Comune catastale');
                $this->table = 'cat_munic';
                $this->fields = $this->defFieldsCatMunic();
                $this->prefix = 'cm';
                break;
            default:
                die("Invalid kind [$this->kind]");
        }
    }

    // Translate the generic field in the specific field
    public function translateFields($request) {
        $request[$this->prefix . '_name_1'] = @$request['popup_name_1'];
        $request[$this->prefix . '_name_2'] = @$request['popup_name_2'];
        unset($request['popup_name_1']);
        unset($request['popup_name_2']);
        return $request;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $this->kind = initVar('kind');
        $this->act = initVar('act', 'add');
        $this->mu_id = initVar('mu_id');
        $this->mu_name = initVar('mu_name');
        if ($this->mu_name <> '') {
            // Convert municipality text into id
            $db = ezcDbInstance::get();
            $lang = R3Locale::getLanguageID();
            $this->mu_id = (int) $db->query("SELECT mu_id FROM municipality WHERE mu_name_{$lang} ILIKE " . $db->quote($request['mu_name']))->fetchColumn();
        }

        setLang(R3Locale::getLanguageCode());
        $this->registerAjaxFunction('submitFormData');
    }

    public function getPageTitle() {
        
    }

    public function getListSQL() {
        
    }

    public function createListTableHeader(&$order) {
        
    }

    public function getData($id = null) {
        
    }

    public function getPageVars() {
        return array('mu_id' => $this->mu_id,
            'kind' => $this->kind);
    }

    public function objectExistsByName($langId, $mu_id, $name) {
        $db = ezcDbInstance::get();
        $sql = "SELECT COUNT(*) AS tot FROM {$this->table} WHERE mu_id=? AND UPPER(TRIM({$this->prefix}_name_{$langId}))=UPPER(TRIM(?))";
        $stmt = $db->prepare($sql);
        $stmt->execute(array($mu_id, $name));
        $tot = $stmt->fetch(PDO::FETCH_ASSOC);
        return $tot['tot'];
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {

        $this->defFields();         // Definisce i campi in base al parametro kind
        $request = $this->translateFields($request);   // Converte i nomi generici della richiesta in nomi specifici (fr, st_cm)

        if (!R3EcoGisHelper::isValidMunicipality($request['mu_id']))
            die("INVALID MUNICIPALITY [{$request['mu_id']}]"); // Security trap
        $errors = $this->checkFormData($request);

        for ($langId = 1; $langId <= R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1); $langId++) {
            if ($this->objectExistsByName($langId, $request['mu_id'], $request["{$this->prefix}_name_{$langId}"])) {
                $errors["popup_name_{$langId}"] = array('CUSTOM_ERROR' => sprintf(_("{$this->lookupName} con nome \"%s\" esiste già"), $request["{$this->prefix}_name_{$langId}"]));
            }
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $id = $this->applyData($request);
            if ($this->kind == 'fraction') {
                $jsFunc = 'addFractionDlgDone';
            } else if ($this->kind == 'street') {
                $jsFunc = 'addStreetDlgDone';
            } else if ($this->kind == 'catmunic') {
                $jsFunc = 'addCatMunicDlgDone';
            }
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "$jsFunc($id)");
        }
    }

    public function checkPerm() {
        $act = 'ADD';
        $name = strtoupper($this->kind);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
