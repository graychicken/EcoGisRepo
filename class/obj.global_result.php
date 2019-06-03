<?php

class eco_global_result extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'global_entry';

    /**
     * ecogis.global_entry fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'ge_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'mu_id', 'type' => 'lookup', 'required' => true, 'label' => _('Comune'), 'lookup' => array('table' => 'municipality')),
            array('name' => 'ge_name_1', 'type' => 'text', 'size' => 80, 'required' => true, 'label' => _('Titolo')),
            array('name' => 'ge_name_2', 'type' => 'text', 'size' => 80, 'label' => _('Titolo')),
            array('name' => 'ge_descr_1', 'type' => 'text', 'label' => _('Descrizione')),
            array('name' => 'ge_descr_2', 'type' => 'text', 'label' => _('Descrizione 2')),
            array('name' => 'ge_year', 'type' => 'year', 'required' => true, 'label' => _('Anno'), 'min_value' => 1990, 'max_value' => 2020),
            array('name' => 'ge_citizen', 'type' => 'integer', 'required' => true, 'label' => _('Abitanti')),
            array('name' => 'ge_national_efe', 'type' => 'float'),
            array('name' => 'ge_local_efe', 'type' => 'float'),
            array('name' => 'ge_green_electricity_purchase', 'type' => 'float'),
            array('name' => 'ge_green_electricity_co2_factor', 'type' => 'float'),
            array('name' => 'ge_non_produced_co2_factor', 'type' => 'float'),
        );
        return $fields;
    }

    /**
     * Return the fields for the list table
     */
    public function getTableColumnConfig() {
        $showMunicipality = R3EcoGisHelper::getMunicipalityCount($this->do_id) > 1;
        $rows = array('mu_name' => array('label' => R3EcoGisHelper::geti18nMunicipalityLabel($this->do_id), 'visible' => $showMunicipality, 'options' => array('order_fields' => 'mu_type, mu_name, ge_year, ge_name, ge_id')),
            'ge_year' => array('label' => _('Anno riferimento'), 'width' => 100, 'type' => 'integer', 'options' => array('order_fields' => 'ge_year, mu_name, ge_name, ge_id')),
            'ge_name' => array('label' => _('Titolo inventario emissioni'), 'options' => array('order_fields' => 'ge_name, ge_year, mu_name, ge_id')),
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

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        if ($init || $reset) {
            $storeVar = true;
        }
        $this->parent_act = initVar('parent_act');
        $this->id = initVar('id');
        $this->last_id = initVar('last_id');
        $this->act = initVar('act', 'list');
        $this->gs_id = initVar('gs_id');
        $this->do_id = PageVar('do_id', $_SESSION['do_id'], $init | $reset, false, $this->baseName, $storeVar);
        $this->pr_id = PageVar('pr_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->ge_name = PageVar('ge_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->ge_year = (int) PageVar('ge_year', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->order = PageVar('order', '1A', $init, false, $this->baseName, $storeVar);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName, $storeVar);
        $this->toggle_subcategory = initVar('toggle_subcategory');

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('askDelGlobalResult');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo inventario emissioni');
            case 'mod': return _('Modifica inventario emissioni');
            case 'show': return _('Visualizza inventario emissioni');
            case 'list': return _('Elenco inventario emissioni');
        }
        return '';  // Unknown title
    }

    /**
     * Return the filter values (list form)
     */
    public function getFilterValues() {
        $filters = array();
        if ($this->auth->getParam('mu_id') == '') {
            $filters['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id, array('join_with_global_result' => true));
            $filters['mu_values'] = R3EcoGisHelper::getMunicipalityAndMunicipalityCollectionList($this->do_id, null, null, array('join_with_global_result' => true));
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
        $filters['ge_name'] = $this->ge_name;
        $filters['ge_year'] = $this->ge_year > 0 ? $this->ge_year : '';
        return $filters;
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $q = $db->createSelectQuery();
        $where = array();
        $where[] = $q->expr->eq('do_id', $this->do_id);
        if (!$this->auth->hasPerm('SHOW', 'ALL_DOMAINS') && $this->auth->getParam('mu_id') <> '') {
            $where[] = $q->expr->eq('mu_id', $db->quote((int) $this->auth->getParam('mu_id')));
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
        if ($this->ge_name <> '') {
            $where[] = "ge_name_{$lang} ILIKE " . $db->quote("%{$this->ge_name}%");
        }
        if ($this->ge_year > 0) {
            $where[] = $q->expr->eq('ge_year', (int) $this->ge_year);
        }
        $q->select("ge_id, cus_name_$lang AS cus_name, mu_id, mu_name_$lang AS mu_name,
                    ge_name_$lang AS ge_name, ge_year, ge_id_strategy, ge_id_2_strategy")
                ->from('global_entry_data');
        if (count($where) > 0) {
            $q->where($where);
        }
        //echo $q;
        return $q;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {
        $tableConfig = new R3BaseTableConfig(R3AuthInstance::get());
        $tableColumns = $tableConfig->getConfig($this->getTableColumnConfig(), $this->baseName);
        foreach ($tableColumns as $fieldName => $colDef) {
            if ($colDef['visible']) {
                $this->simpleTable->addSimpleField($colDef['label'], $fieldName, $colDef['type'], $colDef['width'], $colDef['options']);
            }
        }
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 85);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order, 'global_strategy_list_table');
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['ge_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();
        $locked = false;
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'show':
                        $links['SHOW'] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:showObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'mod':
                        if (!$locked) {
                            $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        } else {
                            $links[] = $this->simpleTable->AddLinkCell('', '', '', "../images/ico_spacer.gif");
                        }
                        break;
                    case 'del':
                        if (!$locked) {
                            $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelGlobalResult('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        } else {
                            $links[] = $this->simpleTable->AddLinkCell('', '', '', "../images/ico_spacer.gif");
                        }
                        break;
                }
            }
        }
        return $links;
    }

    public function getListTableRowStyle(&$row) {
        $style = array();
        if ($row['ge_id_strategy'] <> '' || $row['ge_id_2_strategy'] <> '') {
            $style[] = 'grid_has_exp_date';
        }
        if ($row['ge_id'] == $this->last_id) {
            $style[] = 'selected_row';
        }
        if (count($style) > 0) {
            return array('normal' => implode(' ', $style));
        }
        return array();
    }

    public function getTableLegend() {
        $result[] = array('text' => _('Inventario utilizzato nel Patto dei Sindaci'), 'className' => 'grid_has_exp_date');
        return $result;
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
        if ($this->act <> 'add') {
            $q = $db->createSelectQuery();
            $q->select('*')
                    ->from('global_entry_data')
                    ->where('ge_id=' . (int) $id);
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $vlu['cus_name'] = $vlu['cus_name_' . $lang];
            $vlu['mu_name'] = $vlu['mu_name_' . $lang];  // Autocomplete
            $vlu['ge_year_as_string'] = $vlu['ge_year'];  // Prevent number format
            $vlu['ge_green_electricity_purchase'] = $vlu['ge_green_electricity_purchase'] / 1000;  // Revert to MWh
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('global_entry', $vlu['ge_id']));
        } else {
            $vlu = array();
            $vlu['do_id'] = $_SESSION['do_id'];
            $vlu['cus_name'] = R3EcoGisHelper::getDomainName($_SESSION['do_id']);
            $mu_values = R3EcoGisHelper::getMunicipalityList($this->do_id);
            if (count($mu_values) == 1) {
                $vlu['mu_id'] = key($mu_values);
            } else if ($this->auth->getParam('mu_id') > 0) {
                $vlu['mu_id'] = $this->auth->getParam('mu_id');
            }
            if ($this->auth->getParam('mu_id') > 0) {
                $vlu['mu_id'] = $this->auth->getParam('mu_id');
                $vlu['mu_name'] = R3EcoGisHelper::getMunicipalityName($this->auth->getParam('mu_id'));
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
        if ($this->auth->getParam('mu_id') == '') {
            $lkp['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id);
            $lkp['mu_values'] = R3EcoGisHelper::getMunicipalityAndMunicipalityCollectionList($this->do_id);
        } else {
            $lkp['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        $mu_id = $this->auth->getParam('mu_id');
        if ($this->act == 'add' && count($lkp['mu_values']) == 1) {
            $mu_id = key($lkp['mu_values']);
        } else if ($this->act == 'mod') {
            $mu_id = $this->data['mu_id'];
        }
        return $lkp;
    }

    protected function getTabParametersForGlobalSource($gs_id) {
        $result = array('kind' => '', 'tab_param' => array('consumption' => '', 'emission' => '', 'energy_production' => '', 'heath_production' => ''));
        if ($this->gs_id <> 0) {
            $gs_id = (int) $gs_id;
            $db = ezcDbInstance::get();
            $sql = "SELECT LOWER(gt_code) AS gt_code
                FROM global_type gt
                INNER JOIN global_category_type gcat ON gt.gt_id=gcat.gt_id
                INNER JOIN global_category gc ON gcat.gc_id=gc.gc_id
                INNER JOIN global_subcategory gs on gs.gc_id=gc.gc_id
                WHERE gs_id={$gs_id}";
            $kind = $db->query($sql)->fetchColumn();
            $result['tab_param'][$kind] = "gs_id={$gs_id}&";
            $result['kind'] = $kind;
        }
        return $result;
    }

    public function getPageVars() {

        $tabParams = $this->getTabParametersForGlobalSource($this->gs_id);
        $tabMode = 'ajax';
        $tabMode = 'iframe';
        $tabs = array();
        $tabs[] = array('id' => 'consumption', 'label' => _('Consumo energetico finale'), 'url' => "edit.php?on=global_result_table&kind=consumption&ge_id={$this->id}&parent_act={$this->act}&toggle_subcategory={$this->toggle_subcategory}&init&{$tabParams['tab_param']['consumption']}");
        $tabs[] = array('id' => 'emission', 'label' => _('Emissioni di CO<sub>2</sub>'), 'url' => "edit.php?on=global_result_table&kind=emission&ge_id={$this->id}&parent_act={$this->act}&toggle_subcategory={$this->toggle_subcategory}&init&{$tabParams['tab_param']['emission']}");
        $tabs[] = array('id' => 'energy_production', 'label' => _('Produzione locale di elettricità'), 'url' => "edit.php?on=global_result_table&kind=energy_production&ge_id={$this->id}&parent_act={$this->act}&toggle_subcategory={$this->toggle_subcategory}&init&{$tabParams['tab_param']['energy_production']}");
        $tabs[] = array('id' => 'heath_production', 'label' => _('Produzione locale di calore/freddo'), 'url' => "edit.php?on=global_result_table&kind=heath_production&ge_id={$this->id}&parent_act={$this->act}&toggle_subcategory={$this->toggle_subcategory}&init&{$tabParams['tab_param']['heath_production']}");
        $tabs[] = array('id' => 'doc', 'label' => _('Documenti'), 'url' => "list.php?on=document&type=global_entry&doc_object_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode");
        return array('tabs' => $tabs,
            'tab_mode' => $tabMode,
            'active_tab' => $tabParams['kind'],
            'parent_act' => $this->parent_act,
            'toggle_subcategory' => $this->toggle_subcategory,
            'date_format' => R3Locale::getJQueryDateFormat());
    }

    public function getJSFiles() {
        return $this->includeJS(array($this->baseName . '.js',
                    'mapopenfunc.js'), $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        return array(
            'txtSaveToCalculate' => _("ATTENZIONE!\\n\\nSalvare l'inventario per ricalcolare le emissioni di CO2"),
            'txtImportGlobalResult' => _('Importa inventario emissioni'),
            'txtImportDone' => _('Import avvenuto con successo')
        );
    }

    /**
     * Return the help data (ajax)
     * @param array $request    the request
     * @return text             the help text (usually html)
     */
    public function getHelp($request) {
        require_once R3_LIB_DIR . 'eco_help.php';
        $body = R3Help::getHelpPartFromSection($request['section'], $request['id'], R3Locale::getLanguageCode());
        return array('data' => $body !== null ? $body : '');
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();
        $request = array_merge(array('us_id' => ''), $request);
        $request['ge_id'] = forceInteger($request['id'], 0, false, '.');
        if ($this->act <> 'del') {
            $request['mu_id'] = $this->checkFormDataForMunicipality($request, $errors);
            $errors = $this->checkFormData($request, $errors);
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            if ($this->act == 'mod') {
                $this->removeField('mu_id');
                $this->removeField('ge_year');
            }

            // Convert from MWh to kWh 
            if (isset($request['ge_green_electricity_purchase'])) {
                $request['ge_green_electricity_purchase'] = $request['ge_green_electricity_purchase'] * 1000;
            }
            $id = $this->applyData($request);
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneGlobalResult($id)");
        }
    }

    public function askDelGlobalResult($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = forceInteger($request['id'], 0, false, '.');
        $name = $db->query("SELECT ge_name_$lang FROM global_entry WHERE ge_id={$id}")->fetchColumn();
        if ($this->tryDeleteData($id)) {
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf(_("Sei sicuro di voler cancellare l'inventario emissioni \"%s\"?"), $name));
        } else {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _('Impossibile cancellare questo inventario emissioni poichè vi sono dei dati ad esso legati'));
        }
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        if (!in_array($this->act, array('list', 'add'))) {
            R3Security::checkGlobalResult($this->id);
        }
    }

    function hasDialogMap() {
        return true;
    }

}
