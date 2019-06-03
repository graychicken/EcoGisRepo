<?php

require_once R3_LIB_DIR . 'global_result_table_helper.php';

class eco_global_result_table extends R3AppBaseObject {

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);

        $this->id = initVar('id');
        $this->ge_id = (int) initVar('ge_id');
        $this->gs_id = (int) initVar('gs_id');
        $this->gc_id = initVar('gc_id');  
        $this->act = initVar('act', initVar('parent_act', 'mod'));
        $this->parent_act = initVar('parent_act', 'mod');

        $this->kind = strToUpper(initVar('kind'));
        $this->new_udm_divider = initVar('udm_divider');
        $this->merge_municipality_data = PageVar('merge_municipality_data', 'T') == 'T' ? true : false;
        $this->toggle_subcategory = initVar('toggle_subcategory');

        $this->do_id = PageVar('do_id', $_SESSION['do_id'], $init | $reset, false, $this->baseName);
        $this->pr_id = PageVar('pr_id', null, $init | $reset, false, $this->baseName);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName);
        $this->bpu_id = PageVar('bpu_id', null, $init | $reset, false, $this->baseName);


        $this->order = PageVar('order', '1A', $init, false, $this->baseName);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName);

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('toggleSubcategory');
        $this->registerAjaxFunction('updateLastOpenCloseStatus');
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
        if ($this->auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
            $do_id = null;
            $filters['do_values'] = R3EcoGisHelper::getDomainList();
        } else {
            $do_id = $this->auth->getDomainID();
        }
        $filters['pr_values'] = R3EcoGisHelper::getProvinceList($do_id);
        $filters['mu_values'] = R3EcoGisHelper::getMunicipalityList($do_id);
        $filters['bpu_values'] = R3EcoGisHelper::getBuildingPurposeUseList($do_id);
        $filters['do_id'] = $this->do_id;
        $filters['pr_id'] = $this->pr_id;
        $filters['mu_id'] = $this->mu_id;
        $filters['bpu_id'] = $this->bpu_id;
        return $filters;
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

    /**
     * Return the data for a single customer 
     */
    public function getData($id = null) {
        $lang = R3Locale::getLanguageID();
        if ($id === null) {
            $id = $this->id;
        }
        $db = ezcDbInstance::get();
        $vlu = array();
        if ($this->new_udm_divider <> '') {
            $lastDivider = $this->auth->setConfigValue('SETTINGS', 'GLOBAL_RESULT_LAST_UDM_DIVIDER', $this->new_udm_divider, array('permanent' => true));
        }
        $lastDivider = $this->auth->getConfigValue('SETTINGS', 'GLOBAL_RESULT_LAST_UDM_DIVIDER', 1);
        $this->udm_divider = initVar('udm_divider', $lastDivider);

        if ($this->act <> 'add') {
            $sql = "SELECT mu_type FROM ecogis.global_entry_data WHERE ge_id=" . (int) $this->ge_id;
            $vlu['mu_type'] = $db->query($sql)->fetchColumn();

            $vlu['udm_divider'] = $this->udm_divider;
            $vlu['merge_municipality_data'] = $this->merge_municipality_data;
            $vlu['header'] = R3EcoGisGlobalTableHelper::getParameterList($this->kind);
            $vlu['header']['parameter_count'] = R3EcoGisGlobalTableHelper::getParameterCount($this->kind);
            $vlu['data'] = R3EcoGisGlobalTableHelper::getCategoriesData($this->ge_id, $this->kind, $this->udm_divider, true, $this->gc_id, $this->merge_municipality_data);
        } else {
            $vlu = array(); 
        }
        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Return the data for a single customer 
     */
    public function getLookupData($id = null) {
        $lkp = array();
        if ($this->kind == 'EMISSION') {
            $lkp['udm_divider_values'] = array('1' => 'kg CO2', '1000' => 't CO2');
        } else {
            $lkp['udm_divider_values'] = array('1' => 'kWh', '1000' => 'MWh'); //, '1000000'=>'GWh');
        }
        return $lkp;
    }

    protected function getCategoryIdFromGlobalSubcategory($gs_id) {
        if ($this->gs_id <> 0) {
            $gs_id = (int) $gs_id;
            $db = ezcDbInstance::get();
            $sql = "SELECT gc_id FROM global_subcategory WHERE gs_id={$gs_id}";
            return $db->query($sql)->fetchColumn();
        }
        return null;
    }

    public function getPageVars() {
        $tabMode = 'iframe';

        $result = array('ge_id' => $this->ge_id,
            'gs_id' => $this->gs_id,
            'kind' => $this->kind,
            'open_category' => $this->getCategoryIdFromGlobalSubcategory($this->gs_id), // Forza aperturadettaglio
            'toggle_subcategory' => $this->toggle_subcategory,
            'last_openclose_status' => $this->auth->getConfigValue('SETTINGS', 'GLOBAL_RESULT_LAST_OPEN_CLOSE_STATUS'),
            'parent_act' => $this->parent_act,
            'has_action_column' => true,
            'has_partial_total_row' => true,
            'can_change_udm' => true,
            'tab_mode' => $tabMode,
            'max_inventory_row' => $this->auth->getConfigValue('APPLICATION', 'MAX_INVENTORY_ROW'),
            'date_format' => R3Locale::getJQueryDateFormat());
        $lastOpenCategories = json_decode($this->auth->getConfigValue('SETTINGS', 'GLOBAL_RESULT_LAST_OPEN_CATEGORIES'), true);
        if (!empty($lastOpenCategories[$this->ge_id][strtolower($this->kind)])) {
            $result['last_open_categories'] = implode(',', $lastOpenCategories[$this->ge_id][strtolower($this->kind)]);
        }
        return $result;
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        switch ($this->kind) {
            case 'CONSUMPTION':
            case 'EMISSION':
                $txtShowGlobalConsumption = _('Dettaglio consumi');
                $txtAddGlobalConsumption = _('Aggiungi consumi');
                $txtModGlobalConsumption = _('Modifica consumi');
                break;
            case 'ENERGY_PRODUCTION':
                $txtShowGlobalConsumption = _('Dettaglio produzione locale di elettricità');
                $txtAddGlobalConsumption = _('Aggiungi produzione locale di elettricità');
                $txtModGlobalConsumption = _('Modifica produzione locale di elettricità');
                break;
            case 'HEATH_PRODUCTION':
                $txtShowGlobalConsumption = _('Dettaglio produzione locale di calore/freddo');
                $txtAddGlobalConsumption = _('Aggiungi produzione locale di calore/freddo');
                $txtModGlobalConsumption = _('Modifica produzione locale di calore/freddo');
                break;
        }
        return array('txtCantEditManagedObject' => _('ATTENZIONE! Non è possibile modificare il dettaglio di questi consumi da questa scheda. Utilizzare la relativa scheda di gestione'),
            'txtShowGlobalConsumption' => $txtShowGlobalConsumption,
            'txtAddGlobalConsumption' => $txtAddGlobalConsumption,
            'txtModGlobalConsumption' => $txtModGlobalConsumption,
            'numLanguages' => $this->auth->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1));
    }

    public function updateLastOpenCloseStatus($request) {
        $this->auth->setConfigValue('SETTINGS', 'GLOBAL_RESULT_LAST_OPEN_CLOSE_STATUS', $request['last_openclose_status'], array('persistent' => true));
    }

    public function toggleSubcategory($request) {
        $data = json_decode($this->auth->getConfigValue('SETTINGS', 'GLOBAL_RESULT_LAST_OPEN_CATEGORIES'), true);
        $data[$request['ge_id']][strtolower($request['kind'])] = explode(',', $request['open_categories']);
        $this->auth->setConfigValue('SETTINGS', 'GLOBAL_RESULT_LAST_OPEN_CATEGORIES', json_encode($data), array('persistent' => true));
        $this->auth->setConfigValue('SETTINGS', 'GLOBAL_RESULT_LAST_OPEN_CLOSE_STATUS', 'CLOSE', array('persistent' => true));
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        if (!in_array($this->act, array('list', 'add'))) {
            if (!in_array($this->method, array('updateLastOpenCloseStatus'))) {
                R3Security::checkGlobalResult($this->ge_id);
            }
        }
    }

}
