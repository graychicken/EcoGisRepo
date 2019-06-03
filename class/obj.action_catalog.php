<?php

class R3EcoGisActionCatalogHelper {

    static public function getSubCategoriesListById($do_id, $mu_id, $gc_id = null, array $opt = array()) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $mu_id = (int) $mu_id;
        $gc_id = (int) $gc_id;
        $defaultOptions = array('allow_empty' => false,
            'empty_text' => _('-- Selezionare --'));
        $opt = array_merge($defaultOptions, $opt);
        $sql = "SELECT id, name_$lang AS name
                FROM action_catalog_sub_category
                WHERE mu_id={$mu_id} AND gc_id={$gc_id}
                ORDER BY name_$lang, id, emo_id";
        $result = array();
        if ($opt['allow_empty']) {
            $result[''] = $opt['empty_text'];
        }
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $result[$row['id']] = $row['name'];
        }
        return $result;
    }

    // Simile a R3EcoGisGlobalConsumptionHelper::getEnergySourceList, ma restituisce solo energia elettrica (non posso produrre altro...)
    static public function getProductionEnergySourceList($do_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $opt = $defaultOptions = array('order' => 'gest_order, ges_order, ges_name, ges.ges_id, es_order, es_name, es.es_id, udm_order, udm_name, udm.udm_id');

        // Ricavo fonte geotermica
        $geotermic_es_id = $db->query("SELECT es_id FROM ecogis.energy_source WHERE es_uuid='8def63c2-1116-4426-8490-8270a258c98f'")->fetchColumn();
        $esData = R3EcoGisHelper::getEnergySourceAndUdm($do_id, 'ELECTRICITY');
        $sql = "SELECT ges.ges_id, ges_name_$lang AS ges_name, es.es_id, es_name_$lang AS es_name, udm.udm_id,
                       udm.udm_name_$lang AS udm_name, esu_id, esu_kwh_factor, esu_co2_factor
                FROM ecogis.global_energy_source ges
                INNER JOIN ecogis.global_energy_source_type gest ON ges.ges_id=gest.ges_id
                INNER JOIN ecogis.global_type gt ON gt.gt_id=gest.gt_id AND gt_code='CONSUMPTION'
                INNER JOIN ecogis.energy_source_udm esu ON ges.ges_id=esu.ges_id
                INNER JOIN ecogis.energy_source es ON esu.es_id=es.es_id
                INNER JOIN ecogis.udm ON esu.udm_id=udm.udm_id
                WHERE esu.do_id IS NOT NULL AND esu.mu_id IS NULL AND esu.es_id IN ({$esData['es_id']}, {$geotermic_es_id})
                ORDER BY gest_order, ges_order, ges_name, ges.ges_id, es_order, es_name, es.es_id, udm_order, udm_name, udm.udm_id";
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

}

class eco_action_catalog extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'action_catalog';

    /**
     * ecogis.action_catalog fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'ac_id', 'type' => 'integer', 'label' => _('PK'), 'is_primary_key' => true),
            array('name' => 'mu_id', 'type' => 'lookup', 'required' => true, 'label' => _('Comune'), 'lookup' => array('table' => 'municipality')),
            array('name' => 'wo_id', 'type' => 'lookup', 'label' => _('Intervento edificio'), 'lookup' => array('table' => 'work')),
            array('name' => 'ac_code', 'type' => 'text', 'label' => _('Codice')),
            array('name' => 'ac_name_1', 'type' => 'text', 'required' => true, 'label' => _('Nome')),
            array('name' => 'ac_name_2', 'type' => 'text', 'label' => _('Nome 2')),
            array('name' => 'ac_descr_1', 'type' => 'text', 'label' => _('Descrizione')),
            array('name' => 'ac_descr_2', 'type' => 'text', 'label' => _('Descrizione (2)')),
            array('name' => 'ac_action_descr_1', 'type' => 'text', 'label' => _('Descrizione azione')),
            array('name' => 'ac_action_descr_2', 'type' => 'text', 'label' => _('Descrizione azione (2)')),
            array('name' => 'ac_monitoring_descr_1', 'type' => 'text', 'label' => _('Monitoraggio azione')),
            array('name' => 'ac_monitoring_descr_2', 'type' => 'text', 'label' => _('Monitoraggio azione (2)')),
            array('name' => 'gc_id', 'type' => 'lookup', 'required' => true, 'label' => _('Settore'), 'lookup' => array('table' => 'global_category')),
            array('name' => 'gc_extradata_1', 'type' => 'text', 'label' => _('Extra data')),
            array('name' => 'gc_extradata_2', 'type' => 'text', 'label' => _('Extra data')),
            array('name' => 'gpa_id', 'type' => 'lookup', 'required' => true, 'label' => _('Azione principale'), 'lookup' => array('table' => 'global_plain_action')),
            array('name' => 'gpa_extradata_1', 'type' => 'text', 'label' => _('Extra data')),
            array('name' => 'gpa_extradata_2', 'type' => 'text', 'label' => _('Extra data')),
            array('name' => 'ac_responsible_department_1', 'type' => 'text', 'label' => _('Responsabile')),
            array('name' => 'ac_responsible_department_2', 'type' => 'text', 'label' => _('Responsabile')),
            array('name' => 'ac_start_date', 'type' => 'date', 'required' => true, 'label' => _('Attuazione - dal')),
            array('name' => 'ac_end_date', 'type' => 'date', 'required' => true, 'label' => _('Attuazione - al')),
            array('name' => 'ac_benefit_start_date', 'type' => 'date', 'required' => true, 'label' => _('Beneficio - dal')),
            array('name' => 'ac_benefit_end_date', 'type' => 'date', 'required' => true, 'label' => _('Beneficio - al')),
            array('name' => 'ac_estimated_cost', 'type' => 'float', 'precision' => 2, 'label' => _('Costo stimato')),
            array('name' => 'ac_estimated_public_financing', 'type' => 'float', 'precision' => 2, 'label' => _('Finanziamento pubblico')),
            array('name' => 'ac_estimated_other_financing', 'type' => 'float', 'precision' => 2, 'label' => _('Finanziamento terzi')),
            array('name' => 'ac_effective_cost', 'type' => 'float', 'precision' => 2, 'label' => _('Costo stimato (effettivo)')),
            array('name' => 'ac_effective_public_financing', 'type' => 'float', 'precision' => 2, 'label' => _('Finanziamento pubblico (effettivo)')),
            array('name' => 'ac_effective_other_financing', 'type' => 'float', 'precision' => 2, 'label' => _('Finanziamento terzi (effettivo)')),
            array('name' => 'ac_expected_energy_saving', 'type' => 'float', 'label' => _('Risparmio energetico previsto'), 'calculated' => true),
            array('name' => 'ac_expected_renewable_energy_production', 'type' => 'float', 'label' => _('Produzione di energia rinnovabile prevista')),
            array('name' => 'ac_co2_reduction', 'type' => 'float', 'label' => _('Riduzione di CO2')),
            array('name' => 'ac_green_electricity_purchase', 'type' => 'float', 'label' => _('Acquisto energia')),
            array('name' => 'ac_green_electricity_co2_factor', 'type' => 'float', 'label' => _('Fattore conversione in t CO2 di acquisto energia')),
            array('name' => 'emo_id', 'type' => 'lookup', 'lookup' => array('table' => 'energy_meter_object')),
            array('name' => 'ac_object_id', 'type' => 'lookup'),
            array('name' => 'esu_id_consumption', 'type' => 'lookup', 'label' => _('Risparmio energetico previsto'), 'lookup' => array('table' => 'energy_source_udm', 'field' => 'esu_id'), 'calculated' => true),
            array('name' => 'esu_id_production', 'type' => 'lookup', 'lookup' => array('table' => 'energy_source_udm', 'field' => 'esu_id')),
            array('name' => 'ft_id', 'type' => 'lookup', 'label' => _('Finanziamento'), 'lookup' => array('table' => 'funding_type')),
            array('name' => 'ft_extradata_1', 'type' => 'text', 'label' => _('Descrizione finanziamento')),
            array('name' => 'ft_extradata_2', 'type' => 'text', 'label' => _('Descrizione finanziamento')),
            array('name' => 'ac_alternative_simulation', 'type' => 'boolean', 'label' => _('Simulazione comunale'), 'default' => false),
        );
        return $fields;
    }

    /**
     * Return the fields for the list table
     */
    public function getTableColumnConfig() {
        $showMunicipality = R3EcoGisHelper::getMunicipalityCount($this->do_id) > 1;

        $rows = array('mu_name' => array('label' => R3EcoGisHelper::geti18nMunicipalityLabel($this->do_id), 'visible' => $showMunicipality, 'options' => array('order_fields' => 'mu_type, mu_name, ac_code, ac_name, ac_id')),
            'ac_code' => array('label' => _('Codice'), 'width' => 100, 'options' => array('align' => 'right', 'order_fields' => 'ac_code, ac_name, ac_id')),
            'ac_name' => array('label' => _('Nome')),
            'gc_name' => array('label' => _('Categoria PAES')),
            'gpa_name' => array('label' => _('Azione principale'), 'options' => array('xnumber_format' => array('decimals' => 2))),
            'ac_estimated_auto_financing' => array('label' => _('Autofinanziamento [€]'), 'width' => 100, 'type' => 'float', 'options' => array('order_fields' => 'ac_estimated_auto_financing', 'number_format' => array('decimals' => 2))),
            'ac_expected_energy_saving_mwh' => array('label' => _('Risparmio energetico [MWh]'), 'width' => 100, 'type' => 'float', 'options' => array('order_fields' => 'ac_expected_energy_saving_mwh', 'number_format' => array('decimals' => 2))),
            'ac_expected_renewable_energy_production_mwh' => array('label' => _('Produzione energetica [MWh]'), 'width' => 100, 'type' => 'float', 'options' => array('order_fields' => 'ac_expected_renewable_energy_production_mwh', 'number_format' => array('decimals' => 2))),
            'ac_green_electricity_purchase_mwh' => array('label' => _('Acquisto energia verde [MWh]'), 'width' => 100, 'type' => 'float', 'options' => array('order_fields' => 'ac_green_electricity_purchase_mwh', 'number_format' => array('decimals' => 2))),
            'ac_expected_co2_reduction_calc' => array('label' => _('Riduzione CO2 [t/a]'), 'type' => 'float', 'width' => 100, 'options' => array('order_fields' => 'ac_expected_co2_reduction_calc', 'number_format' => array('decimals' => 2))),
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
        $this->id = initVar('id');
        $this->last_id = initVar('last_id');
        $this->parent_act = initVar('parent_act');
        $this->tab_mode = initVar('tab_mode');
        $this->act = initVar('act', 'list');
        $this->do_id = $_SESSION['do_id'];
        $this->bu_id = initVar('bu_id');
        $this->pr_id = PageVar('pr_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->ac_name = PageVar('ac_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->gc_id = PageVar('gc_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->gpa_name = PageVar('gpa_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->gpa_name = PageVar('ac_alternative_simulation', null, $init | $reset, false, $this->baseName, $storeVar);

        $this->order = PageVar('order', '1A', $init, false, $this->baseName, $storeVar);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName, $storeVar);
        $this->tableURL = array('bu_id' => $this->bu_id);

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('getGlobalCategory');
        $this->registerAjaxFunction('getGlobalSubCategory');
        $this->registerAjaxFunction('getRelatedActionsList');
        $this->registerAjaxFunction('getEnergySource');
        $this->registerAjaxFunction('getEnergyUDM');
        $this->registerAjaxFunction('performActionCatalogCalc');
        $this->registerAjaxFunction('askDelActionCatalog');
        $this->registerAjaxFunction('updateActionName');
        $this->registerAjaxFunction('checkSubActionMapLink');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuova azione');
            case 'mod': return _('Modifica azione');
            case 'show': return _('Visualizza azione');
            case 'list': return _('Catalogo azioni');
        }
        return '';  // Unknown title
    }

    /**
     * Return the filter values (list form)
     */
    public function getFilterValues() {
        $filters = array();
        if ($this->auth->getParam('mu_id') == '') {
            $filters['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id, array('join_with_action_catalog' => true));
            $filters['mu_values'] = R3EcoGisHelper::getMunicipalityAndMunicipalityCollectionList($this->do_id, null, null, array('join_with_action_catalog' => true));
        } else {
            $filters['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }

        $filters['gc_values'] = R3EcoGisHelper::getCategoriesListTreeForFilter($this->do_id, 'action_catalog');
        $filters['gpa_values'] = R3EcoGisHelper::getGlobalplainActionListForFilter($this->do_id, 'action_catalog');

        $filters['do_id'] = $this->getFilterValue('do_id');
        $filters['pr_id'] = $this->getFilterValue('pr_id');
        $filters['mu_id'] = $this->getFilterValue('mu_id');
        $filters['mu_name'] = $this->getFilterValue('mu_name');
        $filters['gc_id'] = $this->getFilterValue('gc_id');
        $filters['gpa_name'] = $this->getFilterValue('gpa_name');
        $filters['ac_alternative_simulation'] = $this->getFilterValue('ac_alternative_simulation');

        $filters['ac_name'] = $this->getFilterValue('ac_name');
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
        if ($this->getFilterValue('pr_id') <> '') {
            $where[] = $q->expr->eq('pr_id', $db->quote((int) $this->getFilterValue('pr_id')));
        }
        if ($this->getFilterValue('mu_id') <> '') {
            $where[] = $q->expr->eq('mu_id', $db->quote((int) $this->getFilterValue('mu_id')));
        }
        if ($this->getFilterValue('mu_name') <> '') {
            $mu_name = $this->getFilterValue('mu_name');
            $where[] = "mu_name_{$lang} ILIKE " . $db->quote("%{$mu_name}%");
        }
        if ($this->getFilterValue('ac_name') <> '') {
            $ac_name = $this->getFilterValue('ac_name');
            $where[] = "(ac_name_1 ILIKE " . $db->quote("%{$ac_name}%") . " OR " .
                    " ac_name_2 ILIKE " . $db->quote("%{$ac_name}%") . " OR " .
                    " ac_code ILIKE " . $db->quote("%{$ac_name}%") . ")";
        }

        if ($this->bu_id <> '') {
            $emo_id = R3EcoGisHelper::getEnergyMeterObjectIdByCode('BUILDING');
            $where[] = "emo_id={$emo_id}";
            $where[] = "ac_object_id=" . (int) $this->bu_id;
        }
        if ($this->getFilterValue('gc_id') <> '') {
            $isMainCategory = substr($this->getFilterValue('gc_id'), 0, 1) == 'M';
            $gc_id = (int) substr($this->gc_id, 1);
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
        if ($this->getFilterValue('ac_alternative_simulation') == 'T') {
            $where[] = "ac_alternative_simulation IS TRUE";
        }


        $q->select("ac_id, ac_code, mu_name_{$lang} AS mu_name, ac_name_{$lang} AS ac_name, gpa_name_{$lang} AS gpa_name,
                    gc_name_{$lang}_parent || ' > ' || gc_name_{$lang} AS gc_name, ac_start_date, ac_end_date, 
                    NULLIF(ac_estimated_cost, 0) AS ac_estimated_cost, 
                    NULLIF(ac_estimated_auto_financing, 0) AS ac_estimated_auto_financing,
                    NULLIF(ac_expected_energy_saving_kwh/1000, 0) AS ac_expected_energy_saving_mwh, 
                    NULLIF(ac_expected_renewable_energy_production_kwh/1000, 0) AS ac_expected_renewable_energy_production_mwh, 
                    NULLIF(ac_green_electricity_purchase_kwh/1000, 0) AS ac_green_electricity_purchase_mwh, 
                    NULLIF((COALESCE(ac_expected_co2_reduction_calc, 0)+COALESCE(ac_co2_reduction, 0))/1000, 0) AS ac_expected_co2_reduction_calc")
                ->from('action_catalog_data');
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
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order, 'global_action_list_table');
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['ac_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;

        $jsSuffix = $this->bu_id == '' ? 'ActionCatalog' : 'ActionCatalogFromBuilding';

        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'show':
                        $links[] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:show{$jsSuffix}('{$this->bu_id}', '{$id}')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:mod{$jsSuffix}('{$this->bu_id}', '{$id}')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'del':
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDel{$jsSuffix}('{$this->bu_id}', '{$id}')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                }
            }
        }
        return $links;
    }

    public function getListTableRowStyle(&$row) {
        if ($row['ac_id'] == $this->last_id) {
            return array('normal' => 'selected_row');
        }
        return array();
    }

    /**
     * Return the data for a single customer
     */
    public function getData($id = null) {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        if ($this->act <> 'add') {
            $q = $db->createSelectQuery();
            $q->select('*')
                    ->from('action_catalog_data ac')
                    ->where("ac_id={$this->id}");
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);

            $vlu['mu_name'] = $vlu["mu_name_{$lang}"];  // Autocomplete
            $vlu['ac_expected_co2_reduction'] = $vlu['ac_expected_co2_reduction_calc'] / 1000; // Get the calculated value - the db value is not used
            $vlu['ac_expected_energy_saving_mwh'] = $vlu['ac_expected_energy_saving_kwh'] / 1000;
            $vlu['ac_green_electricity_purchase_mwh'] = R3NumberFormat($vlu['ac_green_electricity_purchase_kwh'] / 1000, 2, true);
            $vlu['ac_co2_reduction_tco2'] = R3NumberFormat($vlu['ac_co2_reduction'] / 1000, 2, true);
            $vlu['ac_expected_renewable_energy_production_mwh'] = $vlu['ac_expected_renewable_energy_production_kwh'] / 1000; // Get the calculated value - the db value is not used
            // get expected energy savings
            $vlu['ac_expected_energy_savings'] = array();
            $sql = "SELECT action_catalog_energy.*, energy_source_udm.*, " .
                    "      esu_kwh_factor * ace_expected_energy_saving AS ace_expected_energy_saving_kwh, " .
                    "      esu_co2_factor * ace_expected_energy_saving AS ace_expected_co2_reduction_calc " .
                    " FROM action_catalog_energy " .
                    " INNER JOIN energy_source_udm USING(esu_id) " .
                    " WHERE ac_id={$this->id} " .
                    "ORDER BY ace_id";
            $r = $db->query($sql);
            while ($row = $r->fetch()) {
                $lkp = $this->getLookupDataForEnergySavings($row['ges_id']);
                $tmp = array(
                    'ges_id' => $row['ges_id'],
                    'es_id' => $row['es_id'],
                    'udm_id' => $row['udm_id'],
                    'ac_expected_energy_saving' => R3NumberFormat($row['ace_expected_energy_saving'], 2, true),
                    'ac_expected_energy_saving_mwh' => R3NumberFormat($row['ace_expected_energy_saving_kwh'] / 1000, 2, true),
                    'ac_expected_co2_reduction' => R3NumberFormat($row['ace_expected_co2_reduction_calc'] / 1000, 2, true),
                    'es_id_consumption_values' => $lkp['es_id_consumption_values'],
                    'udm_id_consumption_values' => $lkp['udm_id_consumption_values']
                );
                $vlu['ac_expected_energy_savings'][] = $tmp;
            }

            // get related actions
            $vlu['ac_related_actions'] = array();
            $vlu['ac_related_required_actions'] = array();
            $vlu['ac_related_excluded_actions'] = array();
            $sql = "SELECT action_catalog.ac_id, ac_name_$lang, acd_type " .
                    " FROM action_catalog " .
                    " INNER JOIN action_catalog_dependencies on action_catalog.ac_id = action_catalog_dependencies.ac_related_id " .
                    " WHERE action_catalog_dependencies.ac_id={$this->id}";
            $r = $db->query($sql);
            while ($row = $r->fetch()) {
                if ($row['acd_type'] == 'R') {
                    $index = 'ac_related_required_actions';
                } else if ($row['acd_type'] == 'D') {
                    $index = 'ac_related_actions';
                } else if ($row['acd_type'] == 'E') {
                    $index = 'ac_related_excluded_actions';
                } else {
                    throw new exceltion('Invalid value for acd_type');
                }

                $tmp = array(
                    'ac_id' => $row['ac_id'],
                    'ac_name' => $row['ac_name_' . $lang]
                );
                array_push($vlu[$index], $tmp);
            }

            // get benefit year
            $vlu['ac_benefit_year'] = array();
            $sql = "SELECT acby_year, acby_benefit 
                    FROM action_catalog_benefit_year
                    WHERE ac_id={$this->id}
                    ORDER BY acby_year";
            $vlu['enable_benefit_year'] = 'F';
            foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
                $vlu['ac_benefit_year'][] = $row;
                $vlu['enable_benefit_year'] = 'T';
            }
            if ($this->bu_id <> '') {
                $vlu['bu_id'] = $this->bu_id;
            }
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('action_catalog', $vlu['ac_id']));
        } else {
            $vlu = array();
            $vlu['ac_id'] = null;
            $vlu['bu_id'] = $this->bu_id;
            $mu_values = R3EcoGisHelper::getMunicipalityList($this->do_id);
            if (count($mu_values) == 1) {
                $vlu['mu_id'] = key($mu_values);
            } else if ($this->auth->getParam('mu_id') > 0) {
                $vlu['mu_id'] = $this->auth->getParam('mu_id');
            }
            if (isset($vlu['mu_id']) && $vlu['mu_id'] <> '') {
                $vlu['mu_name'] = R3EcoGisHelper::getMunicipalityName($vlu['mu_id']);
            }
            if ($this->bu_id <> '') {
                $vlu['mu_id'] = $db->query('SELECT mu_id FROM building WHERE bu_id=' . (int) $this->bu_id)->fetchColumn();
                $emo_id = R3EcoGisHelper::getEnergyMeterObjectIdByCode('BUILDING');
                $codePart1 = $db->query('SELECT bu_code FROM building WHERE bu_id=' . (int) $this->bu_id)->fetchColumn();
                $codePart2 = $db->query("SELECT LPAD((COUNT(ac_code) + 1)::TEXT, 2, '0') FROM action_catalog WHERE emo_id={$emo_id} AND ac_object_id=" . (int) $this->bu_id)->fetchColumn();
                if ($codePart1 == '') {
                    $vlu['ac_code'] = $codePart2;
                } else {
                    $vlu['ac_code'] = "{$codePart1}-{$codePart2}";
                }
                // Ricavo Macro-settore, Settore, Sub-settore
                list($gc_id, $gc_id_parent) = R3EcoGisHelper::getGlobalCategoryForActionCatalogBuilding($this->bu_id);
                if ($gc_id_parent === null || $gc_id === null) {
                    throw New Exception(_("Destinazione d'uso dell'edificio mancante, o categoria PAES non valida"));
                }
                $vlu['gc_parent_id'] = $gc_id_parent;
                $vlu['gc_id'] = $gc_id;
            } else if (isset($vlu['mu_id'])) {
                $vlu['ac_code'] = (int) $db->query('SELECT MAX(ac_code) FROM action_catalog WHERE mu_id=' . (int) $vlu['mu_id'])->fetchColumn() + 1;
            }
            $vlu['ac_benefit_end_date'] = $this->auth->getConfigValue('APPLICATION', 'DEFAULT_ACTION_BENEFIT_END_DATE', '2020-12-31');
        }

        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    protected function getLookupDataForEnergySavings($ges_id) {
        R3EcoGisHelper::includeHelperClass('obj.global_consumption_row.php');
        R3EcoGisHelper::includeHelperClass('obj.global_plain_row.php');

        $lkp = array();
        $lkp['consumption_energy_source_list'] = R3EcoGisGlobalConsumptionHelper::getEnergySourceList($_SESSION['do_id'], 'CONSUMPTION', array('order' => 'ges_name, gest_order, ges_order, ges.ges_id, es_order, es_name, es.es_id, udm_order, udm_name, udm.udm_id'));

        $lkp['es_id_consumption_values'] = array();
        $lkp['udm_id_consumption_values'] = array();
        foreach ($lkp['consumption_energy_source_list'][$ges_id]['source'] as $esKey => $esVal) {
            $lkp['es_id_consumption_values'][$esKey] = $esVal['name'];
            foreach ($esVal['udm'] as $udmKey => $udmVal) {
                $lkp['udm_id_consumption_values'][$udmKey] = $udmVal['name'];
            }
        }
        return $lkp;
    }

    protected function getActionListForRelatedSelection($muId, $acId = null) { /* TODO: DA SPOSTARE? */
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();

        $actions = array();

        $sql = "SELECT action_catalog.ac_id, COALESCE(ac_code || ' - ' || ac_name_{$lang}, ac_name_{$lang}) AS name " .
                " FROM action_catalog " .
                " WHERE mu_id = " . $db->quote($muId, PDO::PARAM_INT) . " ";
        if (!empty($acId))
            $sql .= " AND ac_id != {$this->id}";
        $sql .= " ORDER BY ac_code, ac_name_{$lang}";
        $r = $db->query($sql);
        while ($row = $r->fetch())
            array_push($actions, $row);
        return $actions;
    }

    protected function getActionName($acId) {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        if ($acId <> '') {
            $sql = "select ac_name_$lang from action_catalog where ac_id=" . $db->quote($acId, PDO::PARAM_INT);
            return $db->query($sql)->fetchColumn(0);
        }
    }

    /**
     * Return the data for a single customer
     */
    public function getLookupData($id = null) {
        R3EcoGisHelper::includeHelperClass('obj.global_consumption_row.php');
        R3EcoGisHelper::includeHelperClass('obj.global_plain_row.php');

        $lkp = array();

        $lkp['ft_id_values'] = R3EcoGisHelper::getWorkFundingTypeList($_SESSION['do_id']);
        if ($this->auth->getParam('mu_id') == '') {
            $lkp['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id);
            $lkp['mu_values'] = R3EcoGisHelper::getMunicipalityAndMunicipalityCollectionList($this->do_id);
        } else {
            $lkp['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        $mu_id = $this->auth->getParam('mu_id');
        if ($this->act == 'add' && count($lkp['mu_values']) == 1) {
            $mu_id = key($lkp['mu_values']);
        } else if ($this->act == 'mod' || $this->act == 'show') {
            $mu_id = $this->data['mu_id'];
        }
        $lkp['gc_parent_values'] = R3EcoGisGlobalPlainHelper::getCategoriesListByparentId($_SESSION['do_id']);
        $lkp['consumption_energy_source_list'] = R3EcoGisGlobalConsumptionHelper::getEnergySourceList($_SESSION['do_id'], 'CONSUMPTION', array('order' => 'ges_name, gest_order, ges_order, ges.ges_id, es_order, es_name, es.es_id, udm_order, udm_name, udm.udm_id'));
        $lkp['production_energy_source_list'] = R3EcoGisActionCatalogHelper::getProductionEnergySourceList($_SESSION['do_id']);

        if ($this->act <> 'add') {
            $lkp['gc_values'] = R3EcoGisGlobalPlainHelper::getCategoriesListByparentId($_SESSION['do_id'], $this->data['gc_parent_id']);
            $lkp['ac_object_values'] = R3EcoGisActionCatalogHelper::getSubCategoriesListById($_SESSION['do_id'], $this->data['mu_id'], $this->data['gc_id']);
            $lkp['gpa_values'] = R3EcoGisGlobalPlainHelper::getPlainActionList($_SESSION['do_id'], $this->data['gc_id']);
            if ($this->data['esu_id_production'] <> '') {
                $lkp['es_id_production_values'] = array();
                $lkp['udm_id_production_values'] = array();
                foreach ($lkp['production_energy_source_list'][$this->data['ges_id_production']]['source'] as $esKey => $esVal) {
                    $lkp['es_id_production_values'][$esKey] = $esVal['name'];
                    foreach ($esVal['udm'] as $udmKey => $udmVal) {
                        $lkp['udm_id_production_values'][$udmKey] = $udmVal['name'];
                    }
                }
            }
        } else {
            if ($this->bu_id <> '') {
                $lkp['gpa_values'] = R3EcoGisGlobalPlainHelper::getPlainActionList($_SESSION['do_id'], $this->data['gc_id']);
            }
        }

        $lkp['ac_related_actions_list'] = array();
        if (!empty($this->data['mu_id'])) {
            $lkp['ac_related_actions_list'] = $this->getActionListForRelatedSelection($this->data['mu_id'], $this->data['ac_id']);
        }
        return $lkp;
    }

    public function getPageVars() {

        return array('bu_id' => $this->bu_id,
            'tab_mode' => $this->tab_mode,
            'parent_act' => $this->parent_act,
            'sector' => R3EcoGisHelper::getGlobalCategoryPathForActionCatalogBuilding($this->bu_id, R3Locale::getLanguageID()),
            'date_format' => R3Locale::getJQueryDateFormat());
    }

    public function getJSFiles() {
        
    }

    public function getJSVars() {
        return array('txtAddActionCatalog' => _('Aggiungi azione'),
            'txtModActionCatalog' => _('Modifica azione'),
            'txtShowActionCatalog' => _('Visualizza azione'),
            'numLanguages' => $this->auth->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1),
            'askDeleteCurrentExpectedEnergySavings' => _('Sei sicuro di voler eliminare il risparmio energetico previsto corrente?'),
            'askDeleteCurrentRelatedActions' => _('Sei sicuro di voler slegare questa azione interdipendente?'),
            'askDeleteCurrentRelatedRequiredActions' => _('Sei sicuro di voler slegare questa azione propedutica?'),
            'askDeleteCurrentExcludedRequiredActions' => _('Sei sicuro di voler slegare questa azione esclusiva?'),
            'askDeleteCurrentBenefitYear' => _('Sei sicuro di voler eliminare questo anno di benficio?'));
    }

    static function getEnergyMeterObjectByID($mu_id, $ac_object_id, $gc_id) {
        $db = ezcDbInstance::get();

        $mu_id = (int) $mu_id;
        $ac_object_id = (int) $ac_object_id;
        $gc_id = (int) $gc_id;
        $sql = "SELECT emo_id
                FROM action_catalog_sub_category
                WHERE mu_id={$mu_id} AND id={$ac_object_id} AND gc_id={$gc_id}";
        if (($val = $db->query($sql)->fetchColumn()) === false) {
            return null;
        }
        return $val;
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();
        $db = ezcDbInstance::get();
        $request = array_merge(array('ac_object_id' => null, 'gc_id' => null), $request);

        $request['wo_id'] = null;
        $request['ac_id'] = $request['id'];
        if (isset($request['mu_name'])) {
            $request['mu_id'] = R3EcoGisHelper::getMunicipalityIdByName($this->do_id, $request['mu_name'], $this->auth->getParam('mu_id'));
        }
        if ($this->act <> 'del') {
            $request['ac_green_electricity_purchase'] = forceFloat($request['ac_green_electricity_purchase_mwh'], null, '.') * 1000;  // Convert MWh to kWh
            $request['ac_co2_reduction'] = forceFloat($request['ac_co2_reduction_tco2'], null, '.') * 1000;  // Convert tCO2 to kCO2
            $tmpGesIdConsumption = array();
            $tmpEsIdConsumption = array();
            $tmpUdmIdConsumption = array();
            $tmpAcExpectedEnergySaving = array();
            $tmpAcExpectedEnergySavingMwh = array();
            for ($i = 0; $i < count($request['es_id_consumption']); $i++) {
                if ($request['udm_id_consumption'][$i] != '' || $request['ac_expected_energy_saving'][$i] != '') {
                    $tmpGesIdConsumption[] = $request['ges_id_consumption'][$i];
                    $tmpEsIdConsumption[] = $request['es_id_consumption'][$i];
                    $tmpUdmIdConsumption[] = $request['udm_id_consumption'][$i];
                    $tmpAcExpectedEnergySaving[] = $request['ac_expected_energy_saving'][$i];
                    $tmpAcExpectedEnergySavingMwh[] = $request['ac_expected_energy_saving_mwh'][$i];
                }
            }
            $request['ges_id_consumption'] = $tmpGesIdConsumption;
            $request['es_id_consumption'] = $tmpEsIdConsumption;
            $request['udm_id_consumption'] = $tmpUdmIdConsumption;
            $request['ac_expected_energy_saving'] = $tmpAcExpectedEnergySaving;
            $request['ac_expected_energy_saving_mwh'] = $tmpAcExpectedEnergySavingMwh;

            $request['esu_id_consumption'] = R3EcoGisHelper::getMultipleEnergySourceUdmID($_SESSION['do_id'], $request['es_id_consumption'], $request['udm_id_consumption'], $request['mu_id']);

            $request['esu_id_production'] = R3EcoGisHelper::getEnergySourceUdmID($_SESSION['do_id'], $request['es_id_production'], $request['udm_id_production'], $request['mu_id'], true);
            $request['emo_id'] = self::getEnergyMeterObjectByID($request['mu_id'], $request['ac_object_id'], $request['gc_id']);

            if (isset($request['mu_name']) && $request['mu_name'] <> '' && $request['mu_id'] == '')
                $errors['mu_name'] = array('CUSTOM_ERROR' => _('Il comune immesso non è stato trovato'));
            if (!isset($request['gc_id_parent']) || $request['gc_id_parent'] == '')
                $errors['gc_id_parent'] = array('CUSTOM_ERROR' => _('Il campo "Macro-settore" è obbligatorio'));
            $errors = $this->checkFormData($request, $errors);

            $selectedRelatedActions = array();
            if (isset($request['related_required_action_id'])) {
                for ($i = 0; $i < count($request['related_required_action_id']); $i++) {
                    if ($request['related_required_action_id'][$i] > 0 && in_array($request['related_required_action_id'][$i], $selectedRelatedActions)) {
                        $errors['related_required_action_' . $i] = array('CUSTOM_ERROR' => _("L'azione ") . $this->getActionName($request['related_required_action_id'][$i]) . _(" è già stata selezionata"));
                    }
                    array_push($selectedRelatedActions, $request['related_required_action_id'][$i]);
                }
            }
            if (isset($request['related_action_id'])) {
                for ($i = 0; $i < count($request['related_action_id']); $i++) {
                    if ($request['related_action_id'][$i] > 0 && in_array($request['related_action_id'][$i], $selectedRelatedActions)) {
                        $errors['related_action_' . $i] = array('CUSTOM_ERROR' => _("L'azione ") . $this->getActionName($request['related_action_id'][$i]) . _(" è già stata selezionata"));
                    }
                    array_push($selectedRelatedActions, $request['related_action_id'][$i]);
                }
            }
            if (isset($request['related_excluded_action_id'])) {
                for ($i = 0; $i < count($request['related_excluded_action_id']); $i++) {
                    if ($request['related_excluded_action_id'][$i] > 0 && in_array($request['related_excluded_action_id'][$i], $selectedRelatedActions)) {
                        $errors['related_required_action_' . $i] = array('CUSTOM_ERROR' => _("L'azione ") . $this->getActionName($request['related_excluded_action_id'][$i]) . _(" è già stata selezionata"));
                    }
                    array_push($selectedRelatedActions, $request['related_excluded_action_id'][$i]);
                }
            }

            if (isset($request['enable_benefit_year']) && $request['enable_benefit_year'] == 'T' && isset($request['benefit_year'])) {
                $startBenefitYear = (int) substr($request['ac_benefit_start_date'], 0, 4);
                $endBenefitYear = (int) substr($request['ac_benefit_end_date'], 0, 4);
                $lastBenefitPerc = 0;
                for ($i = 0; $i < count($request['benefit_year']); $i++) {
                    if ($request['benefit_year'][$i] <> '' && $request['benefit_year'][$i] < $startBenefitYear) {
                        $errors['benefit_year_' . $i] = array('CUSTOM_ERROR' => sprintf(_("L'anno del beneficio \"%s\" è antecedente al %s (anno inizio beneficio)"), $request['benefit_year'][$i], $startBenefitYear));
                    } else if ($request['benefit_year'][$i] <> '' && $request['benefit_year'][$i] > $endBenefitYear) {
                        $errors['benefit_year_' . $i] = array('CUSTOM_ERROR' => sprintf(_("L'anno del beneficio \"%s\" è oltre al %s (anno fine beneficio)"), $request['benefit_year'][$i], $endBenefitYear));
                    }
                    if (($request['benefit_benefit'][$i] <> '' && $request['benefit_benefit'][$i] < 0) ||
                            ($request['benefit_benefit'][$i] <> '' && $request['benefit_benefit'][$i] > 100)) {
                        $errors['benefit_benefit_' . $i] = array('CUSTOM_ERROR' => _("Il valore del beneficio deve essere compreso tra 0 e 100"));
                    }
                }
            }
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            setLocale(LC_ALL, 'C');
            $db->beginTransaction();
            $id = $this->applyData($request);
            $sql = "DELETE FROM ecogis.action_catalog_energy WHERE ac_id=" . $db->quote($id, PDO::PARAM_INT);
            $db->exec($sql);
            if ($this->act <> 'del') {
                for ($i = 0; $i < count($request['esu_id_consumption']); $i++) {
                    $sql = "INSERT INTO ecogis.action_catalog_energy  (ac_id, esu_id, ace_expected_energy_saving) " .
                            " VALUES (" . $db->quote($id, PDO::PARAM_INT) . ", " . $db->quote($request['esu_id_consumption'][$i], PDO::PARAM_INT) . ", " . $db->quote($request['ac_expected_energy_saving'][$i], PDO::PARAM_INT) . ")";
                    $db->exec($sql);
                }
            }

            $sql = "DELETE FROM ecogis.action_catalog_dependencies WHERE ac_id=" . $db->quote($id, PDO::PARAM_INT);
            $db->exec($sql);
            if ($this->act <> 'del') {
                $relatedActions = array();
                if (isset($request['related_action_id'])) {
                    for ($i = 0; $i < count($request['related_action_id']); $i++) {
                        array_push($relatedActions, array('related_action_id' => $request['related_action_id'][$i], 'acd_required' => 'D'));
                    }
                }
                if (isset($request['related_required_action_id'])) {
                    for ($i = 0; $i < count($request['related_required_action_id']); $i++) {
                        array_push($relatedActions, array('related_action_id' => $request['related_required_action_id'][$i], 'acd_required' => 'R'));
                    }
                }
                if (isset($request['related_excluded_action_id'])) {
                    for ($i = 0; $i < count($request['related_excluded_action_id']); $i++) {
                        array_push($relatedActions, array('related_action_id' => $request['related_excluded_action_id'][$i], 'acd_required' => 'E'));
                    }
                }
                foreach ($relatedActions as $relatedAction) {
                    $sql = "INSERT INTO ecogis.action_catalog_dependencies  (ac_id, ac_related_id, acd_type) " .
                            " VALUES (" . $db->quote($id, PDO::PARAM_INT) . ", " . $db->quote($relatedAction['related_action_id'], PDO::PARAM_INT) . ", " . $db->quote($relatedAction['acd_required'], PDO::PARAM_BOOL) . ")";
                    if ($relatedAction['related_action_id'] > 0) {
                        $db->exec($sql);
                    }
                }
            }

            $sql = "DELETE FROM ecogis.action_catalog_benefit_year WHERE ac_id=" . $db->quote($id);
            $db->exec($sql);
            if ($this->act <> 'del') {
                if (isset($request['enable_benefit_year']) && $request['enable_benefit_year'] == 'T') {
                    for ($i = 0; $i < count($request['benefit_year']); $i++) {
                        $year = forceInteger($request['benefit_year'][$i]);
                        $benefit = forceFloat($request['benefit_benefit'][$i], null, '.');
                        if ($year > 1970 && $request['benefit_benefit'][$i] <> '') {
                            $sql = "INSERT INTO ecogis.action_catalog_benefit_year  (ac_id, acby_year, acby_benefit) " .
                                    "VALUES ({$id}, {$year}, {$benefit})";
                            $db->exec($sql);
                        }
                    }
                }
            }

            $db->commit();
            R3EcoGisCacheHelper::resetMapPreviewCache(null);
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));

            if ($this->bu_id == '') {
                return array('status' => R3_AJAX_NO_ERROR, 'js' => "submitFormDataDoneActionCatalog($id)");
            } else {
                return array('status' => R3_AJAX_NO_ERROR, 'js' => "submitFormDataDoneActionCatalogFromBuilding($id)");
            }
        }
    }

    public function askDelActionCatalog($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = forceInteger($request['id'], 0, false, '.');
        $name = $db->query("SELECT ac_name_$lang FROM action_catalog WHERE ac_id={$id}")->fetchColumn();

        $sql = "select count(acd_id) from ecogis.action_catalog_dependencies where ac_related_id=" . $db->quote($id, PDO::PARAM_INT);
        $num = $db->query($sql)->fetchColumn(0);
        if ($num > 0) {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _("Impossibile cancellare questa voce dal catalogo azioni poichè è un'azione correlata ad un'altra azione"));
        }

        if ($this->tryDeleteData($id)) {
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf(_("Sei sicuro di voler cancellare la voce \"%s\" dal catalogo azioni?"), $name));
        } else {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _('Impossibile cancellare questa voce dal catalogo azioni poichè vi sono dei dati ad esso legati'));
        }
    }

    public function getGlobalCategory($request) {
        R3EcoGisHelper::includeHelperClass('obj.global_plain_row.php');
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(
                    R3EcoGisGlobalPlainHelper::getCategoriesListByParentId($_SESSION['do_id'], (int) $request['parent_id'], array('allow_empty' => true))
        ));
    }

    public function getGlobalSubCategory($request) {
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(
                    R3EcoGisActionCatalogHelper::getSubCategoriesListById($_SESSION['do_id'], (int) $request['mu_id'], (int) $request['gc_id'], array('allow_empty' => true))
        ));
    }

    public function getRelatedActionsList($request) {
        if (empty($request['ac_id']))
            $request['ac_id'] = null;
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(
                    $this->getActionListForRelatedSelection($request['mu_id'], $request['ac_id'])
            )
        );
    }

    public function getEnergySource($request) {
        R3EcoGisHelper::includeHelperClass('obj.global_consumption_row.php');
        if ($request['type'] == 'CONSUMPTION') {
            $data = R3EcoGisGlobalConsumptionHelper::getEnergySourceList($_SESSION['do_id'], $request['type'], array('order' => 'ges_name, gest_order, ges_order, ges.ges_id, es_order, es_name, es.es_id, udm_order, udm_name, udm.udm_id'));
        } else {
            $data = R3EcoGisActionCatalogHelper::getProductionEnergySourceList($_SESSION['do_id']);
        }
        $result = array();
        $tot = 0;
        if (isset($data[$request['ges_id']]['source'])) {
            foreach ($data[$request['ges_id']]['source'] as $key => $val) {
                $result[$key] = $val['name'];
                if ($key > 0) {
                    $tot++;
                }
            }
        }
        if ($request['ges_id'] > 0 && $tot == 0) {
            return array('status' => R3_AJAX_ERROR, 'error' => array('code' => -1, 'text' => _('Attenzione: Configurazione mancante per questa fonte')));
        }
        $result = R3Opt::addChooseItem($result, array('allow_empty' => count($result) <> 1));
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(
                    $result
        ));
    }

    public function getEnergyUDM($request) {
        R3EcoGisHelper::includeHelperClass('obj.global_consumption_row.php');
        if ($request['type'] == 'CONSUMPTION') {
            $data = R3EcoGisGlobalConsumptionHelper::getEnergySourceList($_SESSION['do_id'], $request['type'], array('order' => 'ges_name, gest_order, ges_order, ges.ges_id, es_order, es_name, es.es_id, udm_order, udm_name, udm.udm_id'));
        } else {
            $data = R3EcoGisActionCatalogHelper::getProductionEnergySourceList($_SESSION['do_id']);
        }
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

        setlocale(LC_ALL, 'C');

        $energySavingData = R3EcoGisHelper::getEnergySourceUdmData($_SESSION['do_id'], $request['es_id_consumption'], $request['udm_id_consumption'], $request['mu_id']);
        $energyProductionData = R3EcoGisHelper::getEnergySourceUdmData($_SESSION['do_id'], $request['es_id_production'], $request['udm_id_production'], $request['mu_id']);
        $ac_expected_co2_reduction_total = 0;
        $ac_expected_co2_reduction = array();
        $ac_expected_energy_saving_mwh = array();
        foreach ($energySavingData as $i => $val) {
            $ac_expected_co2_reduction_total += ($request['ac_expected_energy_saving'][$i] == '' ? null : ($request['ac_expected_energy_saving'][$i] * $energySavingData[$i]['esu_co2_factor'] / 1000));
            $ac_expected_co2_reduction[$i] = R3NumberFormat($request['ac_expected_energy_saving'][$i] == '' ? null : ($request['ac_expected_energy_saving'][$i] * $energySavingData[$i]['esu_co2_factor'] / 1000), 2, true);
            $ac_expected_energy_saving_mwh[$i] = R3NumberFormat($request['ac_expected_energy_saving'][$i] == '' ? null : ($request['ac_expected_energy_saving'][$i] * $energySavingData[$i]['esu_kwh_factor'] / 1000), 2, true);
        }
        if ($energyProductionData === null) {
            $energyProductionMWh = null;
        } else {
            $energyProductionMWh = $request['ac_expected_renewable_energy_production'] == '' ? null : ($request['ac_expected_renewable_energy_production'] * $energyProductionData[0]['esu_kwh_factor'] / 1000);
        }
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => array('ac_expected_co2_reduction_total' => R3NumberFormat($ac_expected_co2_reduction_total, null, true),
                'ac_expected_co2_reduction' => $ac_expected_co2_reduction,
                'ac_expected_energy_saving_mwh' => $ac_expected_energy_saving_mwh,
                'ac_expected_renewable_energy_production_mwh' => R3NumberFormat($energyProductionMWh, null, true),
        ));
    }

    public function updateActionName($request) {
        $db = ezcDbInstance::get();
        $sql = "SELECT gpa_name_1, gpa_name_2, gpa_has_extradata FROM global_plain_action WHERE gpa_id=" . (int) $request['gpa_id'];
        $data = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        $buildingNames = array();
        if ($this->bu_id <> '') {
            $sql = "SELECT bu_name_1, bu_name_2 FROM building WHERE bu_id=" . (int) $this->bu_id;
            $buildingNames = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        }
        $fullNames = array();
        $newNames = array();
        $numLanguages = (int) $this->auth->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1);
        for ($i = 1; $i <= $numLanguages; $i++) {
            $fullNames[$i] = $data["gpa_name_{$i}"];
            if ($data['gpa_has_extradata'] && trim($request["gpa_extradata_{$i}"]) <> '') {
                $fullNames[$i] .= ' - ' . trim($request["gpa_extradata_{$i}"]);
            }
            if (isset($buildingNames["bu_name_{$i}"]) && $buildingNames["bu_name_{$i}"] <> '') {
                $fullNames[$i] .= ' - ' . trim($buildingNames["bu_name_{$i}"]);
            }
            if ((isset($request['force']) && $request['force']) ||
                    $request["ac_name_{$i}"] == '') {
                $newNames["ac_name_{$i}"] = $fullNames[$i];
            } else {
                $newNames["ac_name_{$i}"] = '';
            }
        }

        return array('status' => R3_AJAX_NO_ERROR,
            'data' => $newNames);
    }

    public function checkSubActionMapLink($request) {
        $db = ezcDbInstance::get();
        $mu_id = (int) $request['mu_id'];
        $gc_id = (int) $request['gc_id'];
        $ac_object_id = (int) $request['ac_object_id'];
        $sql = "SELECT the_geom IS NOT NULL AS has_geomatry, emo_code
                FROM action_catalog_sub_category
                WHERE mu_id={$mu_id} AND gc_id={$gc_id} and id={$ac_object_id}";
        $data = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        if ($data === false) {
            return array('status' => R3_AJAX_NO_ERROR,
                'data' => array('has_geomatry' => false, 'emo_code' => null));
        }
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => $data);
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        if ($this->act == 'list' && $this->bu_id <> '') {
            // Arrivo da edificio
            R3Security::checkActionCatalogForBuilding($this->act, $this->bu_id, $this->id, array('method' => $this->method, 'skip_methods' => array('checkSubActionMapLink')));
        } else if (!in_array($this->act, array('list', 'add'))) {
            R3Security::checkActionCatalog($this->id);
        }
    }

}
