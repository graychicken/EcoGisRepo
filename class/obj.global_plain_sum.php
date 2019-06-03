<?php

class eco_global_plain_sum extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'global_plain_sum';

    /**
     * ecogis.global_plain_sum fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'gps_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'gp_id', 'type' => 'lookup', 'required' => true, 'label' => _('Piano d\'azione'), 'lookup' => array('table' => 'global_plain')),
            array('name' => 'gc_id', 'type' => 'lookup', 'required' => true, 'label' => _('Macro categoria'), 'lookup' => array('table' => 'global_category')),
            array('name' => 'gps_expected_energy_saving', 'type' => 'float', 'precision' => 2, 'label' => _('Obiettivo di risparmio energetico')),
            array('name' => 'gps_expected_renewable_energy_production', 'type' => 'float', 'precision' => 2, 'label' => _('Obiettivo di produzione locale di energia rinnovabile')),
            array('name' => 'gps_expected_co2_reduction', 'type' => 'float', 'precision' => 2, 'label' => _('Obiettivo di riduzione di CO<sub>2</sub>')),
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $this->gc_id = (int) initVar('gc_id');
        $this->gp_id = (int) initVar('gp_id');
        $this->act = initVar('act', 'mod');
        $this->tab_mode = initVar('tab_mode');
        $this->kind = initVar('kind');
        $this->fields = $this->defFields();

        $this->registerAjaxFunction('askDelGlobalPlainSum');
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
        $sql = "SELECT gc_name_$lang AS gc_name, gps_expected_energy_saving, gps_expected_renewable_energy_production, gps_expected_co2_reduction
                FROM global_category gc
                LEFT JOIN global_plain_sum gps ON gc.gc_id=gps.gc_id AND gp_id={$this->gp_id}
                WHERE gc.gc_id={$this->gc_id}";
        $vlu = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        return $vlu;
    }

    public function getPageVars() {
        return array('gc_id' => $this->gc_id,
            'gp_id' => $this->gp_id,
            'kind' => $this->kind);
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function submitFormData($request) {

        $errors = array();
        $db = ezcDbInstance::get();
        $errors = $this->checkFormData($request);
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $request['gps_id'] = $db->query("SELECT gps_id FROM global_plain_sum WHERE gp_id={$request['gp_id']} AND gc_id={$request['gc_id']}")->fetchColumn();
            if ($request['act'] <> 'del') {
                if ($request['gps_id'] > 0) {
                    $request['act'] = $request['gps_expected_energy_saving'] == '' &&
                            $request['gps_expected_renewable_energy_production'] == '' &&
                            $request['gps_expected_co2_reduction'] == '' ? 'del' : 'mod';
                } else {
                    $request['act'] = 'add';
                }
            }
            $id = $this->applyData($request);
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneGlobalPlainSum($id, '$this->kind')");
        }
    }

    public function askDelGlobalPlainSum($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $gc_id = (int) $request['gc_id'];
        $name = $db->query("SELECT gc_name_{$lang} FROM global_category WHERE gc_id={$gc_id}")->fetchColumn();
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => sprintf(_("Sei sicuro di voler eliminare gli obiettivi per la categoria \"%s\"?"), $name));
    }

    public function checkPerm() {
        $db = ezcDbInstance::get();
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = 'GLOBAL_PLAIN_TABLE';
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        R3Security::checkGlobalPlain($this->gp_id);
    }

}
