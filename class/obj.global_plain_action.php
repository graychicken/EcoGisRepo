<?php

require_once R3_CLASS_DIR . 'obj.global_plain_row.php';

class eco_global_plain_action extends eco_global_plain_row {

    /**
     * Return the fields for the list table
     */
    public function getTableColumnConfig() {
        $showMunicipality = R3EcoGisHelper::getMunicipalityCount($this->do_id) > 1;
        $rows = array('mu_name' => array('label' => R3EcoGisHelper::geti18nMunicipalityLabel($this->do_id), 'visible' => $showMunicipality, 'options' => array('order_fields' => 'mu_type, mu_name, gc_name, gpa_name')),
            'gc_name' => array('label' => _('Settore'), 'options' => array('order_fields' => 'gc_name, gpa_name')),
            'gpa_name' => array('label' => _('Azione'), 'options' => array('order_fields' => 'gpa_name, gc_name')),
            'gpr_start_date' => array('label' => _('Inizio'), 'type' => 'date', 'width' => 100, 'options' => array('align' => 'center')),
            'gpr_end_date' => array('label' => _('Fine'), 'type' => 'date', 'width' => 100, 'options' => array('align' => 'center')),
            'gpr_estimated_cost' => array('label' => _('Costi stimati [€]'), 'type' => 'number', 'width' => 80, 'options' => array('format' => '%.2f')),
            'gpr_expected_energy_saving' => array('label' => _('Risparmio energetico previsto [MWh/a]'), 'type' => 'number', 'width' => 80, 'options' => array('format' => '%.2f')),
            'gpr_expected_renewable_energy_production' => array('label' => _('Produzione di energia rinnovabile prevista [MWh/a]'), 'type' => 'number', 'width' => 80, 'options' => array('format' => '%.2f')),
            'gpr_expected_co2_reduction' => array('label' => _('Riduzione di CO2 prevista [t/a]'), 'type' => 'number', 'width' => 80, 'options' => array('format' => '%.2f')),
            'gauge_info' => array('label' => _('Indicatori'), 'type' => 'text', 'width' => 80, 'options' => array('align' => 'right')),
            'progress_energy' => array('label' => _('% Completam. Energia'), 'type' => 'number', 'width' => 80),
            'progress_emission' => array('label' => _('% Completam. Emissioni'), 'type' => 'number', 'width' => 80),
        );
        if (R3AuthInstance::get()->getParam('mu_id') <> '') {
            unset($rows['mu_name']);
        }
        return $rows;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $storeVar = isset($_GET['act']) && $_GET['act'] == 'list';  // if true store the filter variables

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        if ($init || $reset) {
            $storeVar = true;
        }

        $this->gc_id_filter = PageVar('gc_id_filter', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->gp_name = PageVar('gp_name', null, $init | $reset, false, $this->baseName, $storeVar);

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('countGaugeAndMonitor');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuova azione PAES');
            case 'mod': return _('Modifica azione PAES');
            case 'show': return _('Visualizza azione PAES');
            case 'list': return _('Elenco azioni PAES');
        }
        return '';  // Unknown title
    }

    public function getHTMLTable() {
        $this->limit = max(10, $this->auth->getConfigValue('SETTINGS', 'ROW_COUNT', 10));
        return parent::getHTMLTable();
    }

    /**
     * Return the filter values (list form)
     */
    public function getFilterValues() {
        $filters = array();
        if ($this->auth->getParam('mu_id') == '') {
            $filters['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id, array('join_with_global_plain' => true));
            $filters['mu_values'] = R3EcoGisHelper::getMunicipalityAndMunicipalityCollectionList($this->do_id, null, null, array('join_with_global_plain' => true));
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
        $filters['gc_values'] = R3EcoGisHelper::getCategoriesListTreeForFilter($this->do_id, 'global_plain_action');
        $filters['gpa_values'] = R3EcoGisHelper::getGlobalplainActionListForFilter($this->do_id, 'global_plain_action');

        // global_plain_action_list_data
        $filters['do_id'] = $this->getFilterValue('do_id');
        $filters['pr_id'] = $this->getFilterValue('pr_id');
        $filters['mu_id'] = $this->getFilterValue('mu_id');
        $filters['mu_name'] = $this->getFilterValue('mu_name');
        $filters['gc_id_filter'] = $this->getFilterValue('gc_id_filter');
        $filters['gpa_name'] = $this->getFilterValue('gpa_name');

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
        if ($this->getFilterValue('gc_id_filter') <> '') {
            $isMainCategory = substr($this->getFilterValue('gc_id_filter'), 0, 1) == 'M';
            $gc_id = (int) substr($this->getFilterValue('gc_id_filter'), 1);
            if ($isMainCategory) {
                $where[] = "gc_parent_id={$gc_id}";
            } else {
                $where[] = "gc_id={$gc_id}";
            }
        }
        if ($this->getFilterValue('gpa_name') <> '') {
            $gpa_name = $this->getFilterValue('gpa_name');
            $where[] = "gpa_name_{$lang} ILIKE " . $db->quote("%{$gpa_name}%");
        }
        /*
         * global_plain_action_list_data
         */
        $q->select("gp_id, gpr_id, mu_name_{$lang} AS mu_name, gp_name_{$lang} AS gp_name, gc_name_main_{$lang} AS gc_name_main, gc_name_{$lang} AS gc_name,
                    gpa_name_{$lang} AS gpa_name, gpr_start_date, gpr_end_date, gpr_estimated_cost, gpr_expected_energy_saving, gpr_expected_renewable_energy_production, 
                    gpr_imported_row, gauge_info,
                    gpr_expected_co2_reduction, NULL AS progress_emission, NULL AS progress_energy")
                ->from('global_plain_action_list_data');
        if (count($where) > 0) {
            $q->where($where);
        }
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
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order, 'global_plain_list_table');
    }

    public function getListTableRowOperations(&$row) {
        $progress = R3EcoGisGlobalPlainHelper::getActionStatus($row['gpr_id']);
        if ($progress['energy_perc'] <> '') {
            $row['progress_energy'] = sprintf('%.1f', $progress['energy_perc']);
        }
        if ($progress['emission_perc'] <> '') {
            $row['progress_emission'] = sprintf('%.1f', $progress['emission_perc']);
        }

        $id = $row['gpr_id'];
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
                        if (!$row['gpr_imported_row']) {
                            $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelGlobalPlainRowFromGauge('{$id}')", "", "{$this->imagePath}ico_{$act}.gif");
                        } else {
                            $links['DEL'] = $this->simpleTable->AddLinkCell('', '', '', "../images/ico_spacer.gif");
                        }
                        break;
                }
            }
        }
        return $links;
    }

    public function getListTableRowStyle(&$row) {
        if ($row['gpr_id'] == $this->last_id) {
            return array('normal' => 'selected_row');
        } else if ($row['gpr_imported_row'] <> '') {
            return array('normal' => 'grid_has_exp_date');
        }
        return array();
    }

    public function getTableLegend() {
        $result[] = array('text' => _('Azioni importate'), 'className' => 'grid_has_exp_date');
        return $result;
    }

    public function getJSVars() {
        return array('txtPredefinedActionsDialogTitle' => _('Azioni predefinite'));
    }

    public function getPageVars() {
        $tabMode = TAB_MODE;

        $tabs = array();
        if ($this->act == 'mod' || $this->act == 'show') {
            $tabs[] = array('id' => 'indicator', 'label' => _('Indicatori'), 'url' => ("list.php?on=global_plain_gauge&gpr_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode"));
            $tabs[] = array('id' => 'meter', 'label' => _('% Completamento'), 'url' => ("list.php?on=global_plain_meter&gpr_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode"));
        }
        $vars = array(
            'tabs' => $tabs,
            'parent_act' => $this->parent_act,
            'tab_mode' => $tabMode,
            'date_format' => R3Locale::getJQueryDateFormat());
        if ($this->act == 'list') {
            // piano d'azione selezionato
            $vars['gp_id'] = $this->gp_id;
        }
        return $vars;
    }

    public function getLookupData($id = null) {

        $lkp = parent::getLookupData($id);
        if ($this->auth->getParam('mu_id') == '') {
            $lkp['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id);
            $lkp['mu_values'] = R3EcoGisHelper::getMunicipalityAndMunicipalityCollectionList($this->do_id, null, null, array('join_with_global_strategy_paes' => true));
        } else {
            $lkp['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        $mu_id = $this->auth->getParam('mu_id');
        if ($this->act == 'add' && count($lkp['mu_values']) == 1) {
            $mu_id = key($lkp['mu_values']);
        } else if ($this->act == 'mod') {
            $mu_id = $this->data['mu_id'];
        }
        $lkp['gpa_gauge_values'] = R3EcoGisHelper::getGlobalPlainActionTypeList($_SESSION['do_id']);
        return $lkp;
    }

    public function countGaugeAndMonitor($request) {

        $db = ezcDbInstance::get();
        $sql = "SELECT COUNT(*) FROM ecogis.global_plain_gauge WHERE gpr_id=" . (int) $request['gpr_id'];
        $totGauge = $db->query($sql)->fetchColumn();
        $sql = "SELECT COUNT(*) FROM ecogis.global_plain_monitor WHERE gpr_id=" . (int) $request['gpr_id'];
        $totMeter = $db->query($sql)->fetchColumn();

        return array('status' => R3_AJAX_NO_ERROR,
            'data' => array('tot' => $totGauge + $totMeter));
    }

}
