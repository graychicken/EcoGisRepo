<?php

class eco_building extends R3AppBasePhotoObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'building';

    /**
     * building fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'mu_id', 'type' => 'lookup', 'required' => true, 'label' => _('Comune'), 'lookup' => array('table' => 'municipality')),
            array('name' => 'bu_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'bu_code', 'type' => 'text', 'label' => _('Codice edificio')),
            array('name' => 'bu_name_1', 'type' => 'text', 'label' => _('Nome edificio'), 'required' => true),
            array('name' => 'bu_name_2', 'type' => 'text', 'label' => _('Nome edificio')),
            array('name' => 'bt_id', 'type' => 'lookup', 'label' => _('Tipologia costruttiva'), 'lookup' => array('table' => 'building_type')),
            array('name' => 'bt_extradata_1', 'type' => 'text'),
            array('name' => 'bt_extradata_2', 'type' => 'text'),
            array('name' => 'fr_id', 'type' => 'lookup', 'label' => _('Frazione'), 'lookup' => array('table' => 'common.fraction')),
            array('name' => 'st_id', 'type' => 'lookup', 'label' => _('Via'), 'lookup' => array('table' => 'common.street')),
            array('name' => 'bu_nr_civic', 'type' => 'integer', 'label' => _('Civico')),
            array('name' => 'bu_nr_civic_crossed', 'type' => 'text', 'size' => 3, 'label' => _('Barrato (Civico)')),
            array('name' => 'bu_build_year', 'type' => 'year', 'label' => _('Anno costruzione edificio')),
            array('name' => 'bu_restructure_year', 'type' => 'year', 'label' => _('Anno ristrutturazione edificio')),
            array('name' => 'bu_area', 'type' => 'float', 'dec' => 1, 'label' => _('Superficie')),
            array('name' => 'bu_area_heating', 'type' => 'float', 'dec' => 1, 'label' => _('Sup.Riscaldata')),
            array('name' => 'bu_descr_1', 'type' => 'text', 'label' => _('Note')),
            array('name' => 'bu_descr_2', 'type' => 'text', 'label' => _('Note')),
            array('name' => 'bu_extra_descr_1', 'type' => 'text', 'label' => _('Note sito pubblico')),
            array('name' => 'bu_extra_descr_2', 'type' => 'text', 'label' => _('Note sito pubblico')),
            array('name' => 'cm_id', 'type' => 'lookup', 'label' => _('Comune catastale'), 'lookup' => array('table' => 'cat_munic')),
            array('name' => 'cm_number', 'type' => 'text', 'label' => _('Particella catastale')),
            array('name' => 'bu_section', 'type' => 'text', 'label' => _('Sezione')),
            array('name' => 'bu_sheet', 'type' => 'text', 'label' => _('Foglio')),
            array('name' => 'bu_sub', 'type' => 'text', 'label' => _('Part. catastale')),
            array('name' => 'bu_part', 'type' => 'text', 'label' => _('Subalterno')),
            array('name' => 'bu_audit_type', 'type' => 'text', 'size' => 1, 'label' => _('Tipo audit'), 'default' => 'L'),
            array('name' => 'bpu_id', 'type' => 'lookup', 'label' => _('Destinazione d\'uso'), 'lookup' => array('table' => 'building_purpose_use'), 'required' => true),
            array('name' => 'bpu_extradata_1', 'type' => 'text', 'label' => _('Destinazione d\'uso')),
            array('name' => 'bpu_extradata_2', 'type' => 'text', 'label' => _('Destinazione d\'uso')),
            array('name' => 'bby_id', 'type' => 'lookup', 'label' => _('Periodo di costruzione'), 'lookup' => array('table' => 'building_build_year')),
            array('name' => 'bry_id', 'type' => 'lookup', 'label' => _('Periodo di ristrutturazione'), 'lookup' => array('table' => 'building_restructure_year')),
            array('name' => 'bu_restructure_descr_1', 'type' => 'text', 'label' => _('Descrizione ristrutturazione (lingua 1)')),
            array('name' => 'bu_restructure_descr_2', 'type' => 'text', 'label' => _('Descrizione ristrutturazione (lingua 2)')),
            array('name' => 'bu_survey_date', 'type' => 'date', 'label' => _('Data audit')),
            array('name' => 'bu_glass_area', 'type' => 'float', 'dec' => 1, 'label' => _('Superficie vetrata')),
            array('name' => 'bu_usage_h_from', 'type' => 'time', 'label' => _('Ora utilizzo edificio (dalle)')),
            array('name' => 'bu_usage_h_to', 'type' => 'time', 'label' => _('Ora utilizzo edificio (alle)')),
            array('name' => 'bu_usage_days', 'type' => 'integer', 'label' => _('Giorni alla settimana di utilizzo edificio')),
            array('name' => 'bu_usage_weeks', 'type' => 'integer', 'label' => _('Settimane di utilizzo edificio')),
            array('name' => 'ez_id', 'type' => 'lookup', 'size' => 2, 'label' => _('Zona climatica'), 'lookup' => array('table' => 'energy_zone')),
            array('name' => 'ec_id', 'type' => 'lookup', 'label' => _('Classe energetica'), 'lookup' => array('table' => 'energy_class')),
            array('name' => 'ecl_id', 'type' => 'lookup', 'label' => _('Descrizione classe energetica'), 'lookup' => array('table' => 'energy_class_limit')),
            array('name' => 'bu_persons', 'type' => 'integer', 'label' => _('Occupanti')),
            array('name' => 'bu_sv_factor', 'type' => 'float', 'label' => _('Fattore di forma (S/V)')),
            array('name' => 'bu_to_check', 'type' => 'boolean', 'label' => _('Da controllare'), 'default' => false),
            array('name' => 'bu_alternative_simulation', 'type' => 'boolean', 'label' => _('Simulazione comunale'), 'default' => false),
        );
        return $fields;
    }

    /**
     * Return the fields for the list table
     */
    public function getTableColumnConfig() {
        $showMunicipality = R3EcoGisHelper::getMunicipalityCount($this->do_id) > 1;
        $rows = array('mu_name' => array('label' => _('Comune'), 'visible' => $showMunicipality),
            'bu_id' => array('label' => _('ID edificio'), 'width' => 100, 'visible' => false, 'options' => array('align' => 'right')),
            'bu_code' => array('label' => _('Codice edificio'), 'width' => 100, 'options' => array('align' => 'right', 'order_fields' => 'bu_code_pad, bu_name, bu_id')),
            'bpu_name' => array('label' => _("Destinazione d'uso"), 'options' => array('order_fields' => 'bpu_name, bu_code, bu_name, bu_id')),
            'bu_name' => array('label' => _('Nome'), 'options' => array('order_fields' => 'bu_name, bpu_name, bu_id')),
            'bu_address' => array('label' => _('Indirizzo'), 'type' => 'calculated', 'options' => array('order_fields' => 'fr_name, st_name, bu_nr_civic, bu_nr_civic_crossed')),
            'bt_name' => array('label' => _('Tipologia costruttiva'), 'options' => array('order_fields' => 'bt_name, bu_code, bu_name, bu_id')),
            'bu_area_heating' => array('label' => _('Sup.Risc.'), 'width' => 60, 'options' => array('align' => 'right', 'order_fields' => 'bu_area_heating, bu_code, bu_name, bu_id', 'number_format' => array('decimals' => 1))),
            'bu_to_check' => array('label' => _('Da controllare'), 'width' => 70, 'visible' => false, 'options' => array('align' => 'center', 'order_fields' => 'bu_to_check, bu_code, bu_name, bu_id')),
        );
        if (R3AuthInstance::get()->getParam('mu_id') <> '') {
            unset($rows['mu_name']);
        }
        return $rows;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        $this->fields = $this->defFields();

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);

        $storeVar = isset($_GET['act']) && $_GET['act'] == 'list' || $reset || $init;  // if true store the filter variables
        if ($init || $reset) {
            $storeVar = true;
        }

        $this->id = initVar('id');
        if (initVar('bu_id') !== null) {
            $this->id = initVar('bu_id');
        }
        $this->last_id = initVar('last_id');
        $this->parent_act = initVar('parent_act');
        $this->act = initVar('act', 'list');

        $this->do_id = $_SESSION['do_id'];
        $this->pr_id = PageVar('pr_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->fr_id = PageVar('fr_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->fr_name = PageVar('fr_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->st_id = PageVar('st_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->st_name = PageVar('st_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->bu_civic = PageVar('bu_civic', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->bu_code = PageVar('bu_code', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->bu_name = PageVar('bu_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->bpu_id = PageVar('bpu_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->bt_id = PageVar('bt_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->bby_id = PageVar('bby_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->bry_id = PageVar('bry_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->bu_to_check = PageVar('bu_to_check', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->bu_alternative_simulation = PageVar('bu_alternative_simulation', null, $init | $reset, false, $this->baseName, $storeVar);

        $this->order = PageVar('order', '1A', $init, false, $this->baseName, $storeVar);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName, $storeVar);

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('fetch_fr_st_cm');
        $this->registerAjaxFunction('fetch_fraction');
        $this->registerAjaxFunction('getStreetList');
        $this->registerAjaxFunction('fetch_catmunic');
        $this->registerAjaxFunction('fetch_municipality');
        $this->registerAjaxFunction('fetch_eneryClassLimit');

        $this->registerAjaxFunction('askDelBuilding');
        $this->registerAjaxFunction('submitFormData');
        
        $this->registerAjaxFunction('exportBuilding');
        $this->registerAjaxFunction('getExportBuildingStatus');


    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo edificio');
            case 'mod': return _('Modifica edificio');
            case 'show': return _('Visualizza edificio');
            case 'list': return _('Elenco edifici');
        }
        return '';  // Unknown title
    }

    /**
     * Return the filter values (list form)
     */
    public function getFilterValues() {
        $filters = array();
        if ($this->auth->getParam('mu_id') == '') {
            $filters['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id, array('join_with_building' => true));
            $filters['mu_values'] = R3EcoGisHelper::getMunicipalityList($this->do_id, null, null, array('join_with_building' => true));
        } else {
            $filters['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        if (count($filters['mu_values']) == 1) {
            $mu_id = key($filters['mu_values']);
            $filters['fr_values'] = R3EcoGisHelper::getFractionList($this->do_id, $mu_id, array('used_by' => 'building'));
            $filters['st_values'] = R3EcoGisHelper::getStreetList($this->do_id, $mu_id, array('used_by' => 'building', 'use_lkp_name' => true));
        } else {
            $mu_id = null;
        }
        $filters['bpu_values'] = R3EcoGisHelper::getBuildingPurposeUseList($this->do_id, array('mu_id' => $mu_id, 'used_by' => 'building_data'));
        $filters['bt_values'] = R3EcoGisHelper::getBuildingTypeList($this->do_id, array('mu_id' => $mu_id, 'used_by' => 'building_data'));
        $filters['bby_values'] = R3EcogisHelper::getBuildingBuildYearList($this->do_id);
        $filters['bry_values'] = R3EcogisHelper::getBuildingRestructureYearList($this->do_id);
        $filters['do_id'] = $this->do_id;
        $filters['pr_id'] = $this->pr_id;
        $filters['mu_id'] = $this->mu_id;
        $filters['mu_name'] = $this->mu_name;
        $filters['fr_id'] = $this->fr_id;
        $filters['fr_name'] = $this->fr_name;
        $filters['st_id'] = $this->st_id;
        $filters['st_name'] = $this->st_name;
        $filters['bu_civic'] = $this->bu_civic;
        $filters['bu_code'] = $this->bu_code;
        $filters['bu_name'] = $this->bu_name;
        $filters['bpu_id'] = $this->bpu_id;
        $filters['bt_id'] = $this->bt_id;
        $filters['bby_id'] = $this->bby_id;
        $filters['bry_id'] = $this->bry_id;
        $filters['bu_to_check'] = $this->bu_to_check;
        $filters['bu_alternative_simulation'] = $this->bu_alternative_simulation;

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
        if ($this->auth->getParam('mu_id') <> '') {
            $where[] = $q->expr->eq('mu_id', $this->auth->getParam('mu_id'));
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
        if ($this->fr_id <> '') {
            $where[] = $q->expr->eq('fr_id', $db->quote((int) $this->fr_id));
        }
        if ($this->fr_name <> '') {
            $where[] = "fr_name_{$lang} ILIKE " . $db->quote("%{$this->fr_name}%");
        }
        if ($this->st_id <> '') {
            $where[] = $q->expr->eq('st_id', $db->quote((int) $this->st_id));
        }
        if ($this->st_name <> '') {
            $where[] = "st_name_{$lang} ILIKE " . $db->quote("%{$this->st_name}%");
        }
        if ($this->bu_civic <> '') {
            if (strpos($this->bu_civic, '/') > 0) {
                $where[] = "COALESCE(bu_nr_civic::text || COALESCE('/' || bu_nr_civic_crossed, '')) ILIKE " . $db->quote("%{$this->bu_civic}%");
            } else {
                $where[] = "bu_nr_civic=" . (int) $this->bu_civic;
            }
        }
        if ($this->bby_id <> '') {
            $where[] = $q->expr->eq('bby_id', $db->quote((int) $this->bby_id));
        }
        if ($this->bry_id <> '') {
            $where[] = $q->expr->eq('bry_id', $db->quote((int) $this->bry_id));
        }
        if ($this->bu_to_check == 'T') {
            $where[] = "bu_to_check IS TRUE";
        }
        if ($this->bu_alternative_simulation == 'T') {
            $where[] = "bu_alternative_simulation IS TRUE";
        }

        if ($this->bu_code <> '') {
            if ($this->auth->getConfigValue('APPLICATION', 'BUILDING_SHOW_ID') == 'T') {
                $where[] = "(bu_code ILIKE " . $db->quote("%{$this->bu_code}%") . " OR bu_id=" . (int) $this->bu_code . ")";
            } else {
                $where[] = "bu_code ILIKE " . $db->quote("%{$this->bu_code}%");
            }
        }
        if ($this->bu_name <> '') {
            $where[] = "(bu_name_1 ILIKE " . $db->quote("%{$this->bu_name}%") . " OR bu_name_2 ILIKE " . $db->quote("%{$this->bu_name}%") . ")";
        }
        if ($this->bpu_id <> '') {
            $where[] = $q->expr->eq('bpu_id', $db->quote((int) $this->bpu_id));
        }
        if ($this->bt_id <> '') {
            $where[] = $q->expr->eq('bt_id', $db->quote((int) $this->bt_id));
        }
        $q->select("do_id, cus_name_$lang AS cus_name, mu_name_$lang AS mu_name, bu_code, bu_id, bu_code, bu_name_$lang AS bu_name, " .
                        "bt_name_$lang AS bt_name, bu_area_heating, mu_id, bu_audit_type, bpu_name_$lang AS bpu_name, " .
                        "fr_name_$lang AS fr_name, st_name_$lang AS st_name, bu_nr_civic, bu_nr_civic_crossed, has_geometry, " .
                        "CASE WHEN bu_to_check IS TRUE THEN 'X' END AS bu_to_check, im_id")
                ->from('ecogis.building_data');
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
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order, 'building_list_table');
    }

    public function getListTableRowOperations(&$row) {
        $address = $row['st_name'] <> '' ? $row['st_name'] : $row['fr_name'];
        $address .= $row['bu_nr_civic'] <> '' ? ', ' : ' ';
        $address .= trim($row['bu_nr_civic_crossed']) <> '' ? $row['bu_nr_civic'] . '/' . $row['bu_nr_civic_crossed'] : $row['bu_nr_civic'];

        $this->simpleTable->addCalcValue('bu_address', trim($address));

        $id = $row['bu_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'show':
                        if ($this->auth->hasPerm('SHOW', 'MAP')) {
                            if ($row['has_geometry']) {
                                if (defined('GISCLIENT') && GISCLIENT == true) {
                                    $links['MAP'] = $this->simpleTable->AddLinkCell(_('Visualizza su mappa'), "javascript:$.fn.zoomToMap({obj_t: 'building', obj_key: 'bu_id', obj_id: {$id}, highlight: true, windowMode: false, featureType: 'g_building.building'});", "", "{$this->imagePath}ico_map.gif");
                                } else {
                                    $links['MAP'] = $this->simpleTable->AddLinkCell(_('Visualizza su mappa'), "javascript:showObjectOnMap('$id')", "", "{$this->imagePath}ico_map.gif");
                                }
                            } else {
                                $links['MAP'] = $this->simpleTable->AddLinkCell('', '', '', "../images/ico_spacer.gif");
                            }
                        }
                        $links['SHOW'] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:showObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'del':
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelBuilding('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                }
            }
        }
        return $links;
    }

    public function getListTableRowStyle(&$row) {
        if ($row['bu_id'] == $this->last_id) {
            return array('normal' => 'selected_row');
        } else if ($row['im_id'] <> '') {
            return array('normal' => 'imported_row');
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
                    ->from('building_data')
                    ->where("bu_id={$this->id}");
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);

            $vlu['mu_name'] = $vlu["mu_name_{$lang}"];  // Autocomplete
            $vlu['fr_name'] = $vlu["fr_name_{$lang}"];  // Autocomplete
            $vlu['st_name'] = $vlu["st_name_{$lang}"];  // Autocomplete
            $vlu['bu_usage_h_from'] = substr($vlu['bu_usage_h_from'], 0, 5);
            $vlu['bu_usage_h_to'] = substr($vlu['bu_usage_h_to'], 0, 5);
            $vlu['bu_build_year_as_string'] = $vlu['bu_build_year'];  // Prevent number format
            $vlu['bu_restructure_year_as_string'] = $vlu['bu_restructure_year'];  // Prevent number format
            // Immgini
            $sql = "SELECT doc_file_id, doct_code
                    FROM document doc
                    INNER JOIN document_type doct ON doc.doct_id=doct.doct_id
                    WHERE doc_object_id={$this->id} AND doct_code IN ('BUILDING_PHOTO', 'BUILDING_THERMOGRAPHY', 'BUILDING_LABEL')";
            // echo $sql;
            $images = array();
            foreach ($db->query($sql) as $row) {
                $images[strtolower($row['doct_code'])][] = $row['doc_file_id'];
            }
            $vlu['images'] = $images;
            if ($vlu['has_geometry']) {
                $vlu['map_preview_url'] = htmlspecialchars(R3EcoGisHelper::getMapPreviewURL($this->do_id, 'building', $this->id, $lang));
            }

            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('building', $vlu['bu_id']));
        } else {
            $vlu = array();
            $vlu['do_id'] = $this->do_id;
            $vlu['cus_name'] = R3EcoGisHelper::getDomainName($this->do_id);
            $mu_values = R3EcoGisHelper::getMunicipalityList($this->do_id);
            if (count($mu_values) == 1) {
                $vlu['mu_id'] = key($mu_values);
            } else if ($this->auth->getParam('mu_id') > 0) {
                $vlu['mu_id'] = $this->auth->getParam('mu_id');
            }
            if (isset($vlu['mu_id']) && $vlu['mu_id'] <> '') {
                $vlu['mu_name'] = R3EcoGisHelper::getMunicipalityName($vlu['mu_id']);
                if ($this->auth->getConfigValue('APPLICATION', 'BUILDING_CODE_TYPE') == 'AUTO' ||
                        $this->auth->getConfigValue('APPLICATION', 'BUILDING_CODE_TYPE') == 'PROPOSED') {
                    $fmt = $this->auth->getConfigValue('APPLICATION', 'BUILDING_CODE_FORMAT');
                    try {
                        $vlu['bu_code'] = sprintf($fmt == '' ? '%s' : $fmt, $db->query("SELECT MAX(bu_code::integer) FROM building WHERE mu_id={$vlu['mu_id']}")->fetchColumn() + 1);
                    } catch (Exception $e) {
                        
                    }
                }
            }
            $vlu['ez_id'] = null;
            $vlu['ec_id'] = null;
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
            $lkp['mu_values'] = R3EcoGisHelper::getMunicipalityList($this->do_id);
        } else {
            $lkp['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        $mu_id = $this->auth->getParam('mu_id');
        if ($this->act == 'add' && count($lkp['mu_values']) == 1) {
            $mu_id = key($lkp['mu_values']);
        } else if ($this->act == 'mod' || $this->act == 'show') {
            $mu_id = $this->data['mu_id'];
        }
        if ($mu_id != '') {
            $lkp['fr_values'] = R3EcogisHelper::getFractionList($this->do_id, $mu_id);
            $lkp['st_values'] = R3EcogisHelper::getStreetList($this->do_id, $mu_id, array('use_lkp_name' => true));
            $lkp['cm_values'] = R3EcogisHelper::getCatMunicList($this->do_id, $mu_id);
        }
        $lkp['bt_values'] = R3EcogisHelper::getBuildingTypeList($this->do_id);
        $lkp['bpu_values'] = R3EcogisHelper::getBuildingPurposeUseList($this->do_id);
        $lkp['bby_values'] = R3EcogisHelper::getBuildingBuildYearList($this->do_id);
        $lkp['bry_values'] = R3EcogisHelper::getBuildingRestructureYearList($this->do_id);
        $lkp['bu_hour_from_values'] = R3EcogisHelper::getBuildingUsageHourList($this->do_id, false);
        $lkp['bu_hour_to_values'] = R3EcogisHelper::getBuildingUsageHourList($this->do_id, true);
        $lkp['bu_day_values'] = R3EcogisHelper::getBuildingUsageDayList($this->do_id);

        $lkp['ez_values'] = R3EcogisHelper::getEnergyZoneList($this->do_id);
        $lkp['ec_values'] = R3EcogisHelper::getEnergyClassList($this->do_id);

        if ($this->data['ez_id'] <> '' && $this->data['ec_id'] <> '') {
            $lkp['ecl_values'] = R3EcogisHelper::getEnergyClassLimitList($this->data['ez_id'], $this->data['ec_id'], $this->do_id);
        }
        return $lkp;
    }

    public function hasSensorData($bu_id) {
        $db = ezcDbInstance::get();
        return $db->query("SELECT bu_id FROM sensor_data WHERE bu_id=" . (int) $bu_id . " LIMIT 1")->fetchColumn() > 0;
    }

    public function hasWork($bu_id) {
        $db = ezcDbInstance::get();
        return $db->query("SELECT bu_id FROM work WHERE bu_id=" . (int) $bu_id . " LIMIT 1")->fetchColumn() > 0;
    }

    public function getPageVars() {
        $tabMode = TAB_MODE;

        $tabs = array();
        if ($this->act == 'mod' || $this->act == 'show') {
            $hasSensorData = $this->hasSensorData($this->data['bu_id']);
            $hasWork = $this->hasWork($this->data['bu_id']);
            if ($this->auth->hasPerm('SHOW', 'STATISTIC')) {
                $tabs[] = array('id' => 'statistic', 'label' => _('Statistiche'), 'url' => ("edit.php?on=building_statistic&bu_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode"));
            }
            $tabs[] = array('id' => 'heating', 'label' => _('Riscaldamento'), 'url' => ("edit.php?on=consumption_tree&kind=heating&bu_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode"));
            $tabs[] = array('id' => 'electricity', 'label' => _('Elettricità'), 'url' => ("edit.php?on=consumption_tree&kind=electricity&bu_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode"));
            $tabs[] = array('id' => 'water', 'label' => _('Acque'), 'url' => ("edit.php?on=consumption_tree&kind=water&bu_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode"));
            if ($hasWork) {
                $tabs[] = array('id' => 'work', 'label' => _('Interventi'), 'url' => ("list.php?on=work&bu_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode"));
            }
            if ($this->auth->hasPerm('SHOW', 'ACTION_CATALOG')) {
                $tabs[] = array('id' => 'action_catalog', 'label' => _('Azioni'), 'url' => ("list.php?on=action_catalog&bu_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode&init"));
            }
            if ($hasSensorData) {
                $tabs[] = array('id' => 'sensor_data', 'label' => _('Sensori ambientali'), 'url' => ("edit.php?on=sensor_data&bu_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode"));
            }
            $tabs[] = array('id' => 'doc', 'label' => _('Documenti'), 'url' => ("list.php?on=document&type=building&doc_object_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode&init"));
        }

        return array('session_id' => session_id(),
            'tollerance' => '20%',
            'tabs' => $tabs,
            'parent_act' => $this->parent_act,
            'tab_mode' => $tabMode,
            'date_format' => R3Locale::getJQueryDateFormat());
    }

    // Return the previewmap definition array or null if no preview map
    public function getPreviewMap() {
        return array('object_type' => $this->baseName,
            'id_key' => $this->getPrimaryKeyName(),
            'object_id' => $this->id,
            'highlight' => 'true',
            'geomType' => 'polygon',
            'featureType' => $this->getFeatureType(),
            'numGeom' => 1);
    }

    public function getJSFiles() {
        if (defined('R3_SINGLE_JS') && R3_SINGLE_JS === true) {
            return array();
        }
        return $this->includeJS(array($this->baseName . '.js',
                    'mapopenfunc.js'), $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        $mapres = explode('x', $this->auth->getConfigValue('SETTINGS', 'MAP_RES', '1024x768'));
        list($thumbWidth, $thumbHeight) = explode('x', $this->auth->getConfigValue('APPLICATION', 'PHOTO_PREVIEW_SIZE', '200x200'));
        return array('txtNewFraction' => _('Aggiungi frazione'),
            'txtNewStreet' => _('Aggiungi indirizzo'),
            'txtNewCatMunic' => _('Aggiungi comune catastale'),
            'txtDeniedUpload' => _('Impossibile caricare un documento con estensione \"$ext\"'), // jQuery plugin require $ext
            'txtDuplicateUpload' => _('Un file con lo stesso nome è già stato selezionato.\nFile: \"$file\"'), // jQuery plugin require $ext
            'txtExport' => _('Export'),
            'UserMapWidth' => $mapres[0],
            'UserMapHeight' => $mapres[1],
            'PopupErrorMsg' => _('ATTENZIONE!\n\nBlocco dei popup attivo. Impossibile aprire la mappa. Disabilitare il blocco dei popup del browser e riprovare'),
            'MapFileName' => '../map/index.php',
            'MapName' => 'ECOGIS',
            'thumbWidth' => $thumbWidth,
            'thumbHeight' => $thumbHeight,
        );
    }

    // Not used
    public function deliver($kind) {
        
    }

    /**
     * Return the ID of images to delete
     * @param <type> $kind
     */
    public function getOldImagesIDs($id, $kind) {
        $db = ezcDbInstance::get();

        $sql = "SELECT doc_file_id
                FROM document doc
                INNER JOIN document_type doct ON doc.doct_id=doct.doct_id
                WHERE doc_object_id=$id";
        if ($kind !== null) {
            $sql .= " AND doct_code=" . $db->quote(strtoupper("BUILDING_{$kind}"));
        }
        $sql .= " ORDER BY doc_file_id";
        return $db->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Delete the images from doc_file_id
     * @param <type> $kind
     */
    public function removeOldFilesByIDs($ids) {
        $db = ezcDbInstance::get();

        $archiveTypeId = $db->query("SELECT doct_id FROM document_type WHERE doct_code='BUILDING'")->fetchColumn();
        $descrData = array();
        foreach ($db->query("SELECT doct_id, doct_descr_1, doct_descr_2 FROM document_type") as $row) {
            $descrData[$row['doct_id']] = $row;
        }

        if (!is_array($ids)) {
            $ids = array($ids);
        }
        foreach ($ids as $id) {
            $sql = "SELECT doc.doct_id, doc_file, doct_code
                    FROM document doc
                    INNER JOIN document_type doct ON doc.doct_id=doct.doct_id
                    WHERE doc_file_id={$id}";
            $data = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
            $descr1 = $db->quote($descrData[$data['doct_id']]['doct_descr_1']);
            $descr2 = $db->quote($descrData[$data['doct_id']]['doct_descr_2']);
            $sql = "UPDATE document SET doct_id={$archiveTypeId}, doc_title_1={$descr1}, doc_title_2={$descr2} WHERE doc_file_id={$id}";
            $db->exec($sql);
            $this->moveOldFile($data['doc_file'], 'building', strtolower(substr($data['doct_code'], 9)), $id);
        }
    }

    /**
     * Check if a building code is unique or not
     * @param integer $mu_id     municipality id
     * @param array $bu_code     building code
     * @param array $old_bu_id   old code of the building
     * @return boolean
     */
    protected function checkBuildingCodeUnique($mu_id, $bu_code, $old_bu_id) {
        $mu_id = (int) $mu_id;
        $bu_code = trim($bu_code);
        $old_bu_id = (int) $old_bu_id;
        $db = ezcDbInstance::get();
        $sql = "SELECT COUNT(*) FROM building WHERE mu_id={$mu_id} AND bu_code=" . $db->quote($bu_code) . " AND bu_id<>{$old_bu_id}";
        return $db->query($sql)->fetchColumn() == 0;
    }

    /**
     * Check the compatibility between the building purpose use and the actions for the given building
     * @param integer $bu_id   building id
     */
    protected function checkBuildingPurposeUseCompatibility($bu_id, $new_bpu_id, array &$errors) {
        $db = ezcDbInstance::get();

        $bu_id = (int) $bu_id;
        $new_bpu_id = (int) $new_bpu_id;

        $sql = "SELECT gc_id FROM building_purpose_use WHERE bpu_id={$new_bpu_id}";
        $new_gc_id = $db->query($sql)->fetchColumn();
        if ($new_gc_id === null) {
            // La destinazione d'uso non ha azione patto dei sindaci
            return true;
        }

        $lang = R3Locale::getLanguageID();
        $emo_id = R3EcoGisHelper::getEnergyMeterObjectIdByCode('BUILDING');
        $sql = "SELECT COALESCE(ac_code || ' - ', '') || ac_name_{$lang} AS ac_name, gpac.gc_id
                FROM ecogis.action_catalog ac
                LEFT JOIN ecogis.global_plain_action_category gpac ON ac.gpa_id=gpac.gpa_id AND gpac.gc_id={$new_gc_id}
                WHERE emo_id={$emo_id} AND ac_object_id={$bu_id} and gpac.gc_id IS NULL
                ORDER BY ac_name";
        // echo $sql;
        $invalidActions = array();
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $invalidActions[] = $row['ac_name'];
        }
        if (count($invalidActions) > 0) {
            $errors['ac_id'] = array('CUSTOM_ERROR' => _("Sono presenti azioni NON compatibili con la destinazione d'uso selezionata. Azioni non campatibili: \n  ") . implode("\n  ", $invalidActions));
            return false;
        }
        return true;
    }

    /**
     * Check the compatibility between the building purpose use and the actions for the given building
     * @param integer $bu_id   building id
     */
    protected function updateBuildingPurposeUseCompatibility($bu_id, $new_bpu_id) {
        $db = ezcDbInstance::get();

        $bu_id = (int) $bu_id;
        $new_bpu_id = (int) $new_bpu_id;

        $emo_id = R3EcoGisHelper::getEnergyMeterObjectIdByCode('BUILDING');

        $sql = "SELECT gc_id FROM building_purpose_use WHERE bpu_id={$new_bpu_id}";
        $new_gc_id = $db->query($sql)->fetchColumn();
        if ($new_gc_id !== null) {
            $sql = "UPDATE action_catalog SET gc_id={$new_gc_id} WHERE gc_id<>{$new_gc_id} AND emo_id={$emo_id} AND ac_object_id={$bu_id}";
            $db->exec($sql);
        }
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();
        $db = ezcDbInstance::get();
        $auth = R3AuthInstance::get();
        $this->setMunicipalityForUser($request);

        if ($this->act <> 'add' && !defined('UNIT_TEST_MODE')) {
            // Security check
            R3Security::checkBuilding(@$request['id']);
        }

        $request['bu_id'] = $request['id'];
        if (isset($request['mu_name'])) {
            $request['mu_id'] = R3EcoGisHelper::getMunicipalityIdByName($this->do_id, $request['mu_name'], $this->auth->getParam('mu_id'));
        }
        if (isset($request['fr_name'])) {
            $request['fr_id'] = R3EcoGisHelper::getFractionIdByName($this->do_id, $request['mu_id'], $request['fr_name']);
        }
        if (isset($request['st_name'])) {
            $request['st_id'] = R3EcoGisHelper::getStreetIdByName($this->do_id, $request['mu_id'], $request['st_name']);
        }
        if ($this->act <> 'del') {
            $errors = $this->checkFormData($request);
            if ($auth->getConfigValue('APPLICATION', 'BUILDING_CODE_REQUIRED') == 'T' && trim($request['bu_code']) == '') {
                $errors['bu_code'] = array('CUSTOM_ERROR' => _('Il campo "Codice edificio" è obbligatorio'));
            }
            if (isset($request['mu_name']) && $request['mu_name'] <> '' && $request['mu_id'] == '') {
                $errors['mu_name'] = array('CUSTOM_ERROR' => _('Il comune immesso non è stato trovato'));
            }
            if (isset($request['fr_name']) && $request['fr_name'] <> '' && $request['fr_id'] == '') {
                $errors['fr_name'] = array('CUSTOM_ERROR' => _('La frazione immessa non è stata trovata'));
            }
            if (isset($request['st_name']) && $request['st_name'] <> '' && $request['st_id'] == '') {
                $errors['st_name'] = array('CUSTOM_ERROR' => _('La strada immessa non è stata trovata'));
            }
            if ($request['bu_usage_weeks'] > 52) {
                $errors['bu_usage_weeks'] = array('CUSTOM_ERROR' => _('Numero di settimane massimo inseribile: 52'));
            }
            if ($auth->getConfigValue('APPLICATION', 'BUILDING_CODE_UNIQUE') == 'T' && !$this->checkBuildingCodeUnique($request['mu_id'], $request['bu_code'], $request['id'])) {
                $errors['bu_code'] = array('CUSTOM_ERROR' => _('Il campo "Codice edificio" non è univoco'));
            }
            if ($this->act == 'mod') {
                // Check compatibility between action and building purpose use
                $this->checkBuildingPurposeUseCompatibility($request['id'], $request['bpu_id'], $errors);
            }
        }

        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $deleteIDs = array(); // Array che contiene l'elenco delle immagini da cancellare (processato dopo il commit per evitare perdita di dati!)
            $db->beginTransaction();

            if ($this->act <> 'del') {
                $id = $this->applyData($request);
                // Update compatibility between action and building purpose use
                $this->updateBuildingPurposeUseCompatibility($request['id'], $request['bpu_id']);

                // Delete photo marked as delete
                foreach ($this->getOldImagesIDs($id, null) as $photoID) {
                    if (isset($request["photo_{$photoID}_delete"]) ||
                            isset($request["label_{$photoID}_delete"]) ||
                            isset($request["thermography_{$photoID}_delete"])) {
                        $deleteIDs[] = $photoID;
                    }
                }

                // Save images
                $uploads = array('bu_photo' => 'photo', 'bu_label' => 'label', 'bu_thermography' => 'thermography');
                foreach ($uploads as $upload => $kind) {
                    if (isset($_FILES[$upload])) {
                        $tot = count($_FILES[$upload]['name']);
                        for ($i = 0; $i < $tot; $i++) {
                            if ($_FILES[$upload]['error'][$i] == 0) {
                                $deleteIDs = array_merge($deleteIDs, $this->getOldImagesIDs($id, $kind));
                                $doc_file_id = $this->getDocFileId();
                                $this->addFile($_FILES[$upload]['name'][$i], 'building', $kind, $doc_file_id, $_FILES[$upload]['tmp_name'][$i]);
                                $doct_id = R3EcoGisHelper::getDocumentTypeIdByCode(strtoupper("BUILDING_{$kind}"));
                                $sql = "INSERT INTO document (doc_object_id, doct_id, doc_file_id, doc_file, doc_date) VALUES ($id, $doct_id, $doc_file_id, " . $db->quote($_FILES[$upload]['name'][$i]) . ", NOW()) ";
                                $db->exec($sql);
                            }
                        }
                    }
                }
                // Save map
                if (isset($request['geometryStatus']) && strtoupper($request['geometryStatus']) == 'CHANGED') {
                    $session_id = session_id();
                    $sql = "UPDATE building
                            SET the_geom=foo.the_geom
                            FROM (SELECT ST_Multi(ST_Force_2d(ST_union(ST_Buffer(the_geom, 0.0)))) AS the_geom FROM edit_tmp_polygon WHERE session_id='{$session_id}') AS foo
                            WHERE bu_id=$id";
                    $db->exec($sql);
                }
            } else {
                $id = $this->applyData($request);
                $deleteIDs = $this->getOldImagesIDs($id, null);      // Delete old images
            }
            $db->commit();
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            $this->removeOldFilesByIDs(array_unique($deleteIDs));

            R3EcoGisCacheHelper::resetMapPreviewCache(null);

            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneBuilding($id)");
        }
    }

    /**
     * Return the help data (ajax)
     * @param array $request    the request
     * @return text             the help text (usually html)
     */
    public function getHelp($request) {
        global $smarty;

        require_once R3_LIB_DIR . 'eco_help.php';
        $smarty->assign('ELECTRICITY_KWH_FACTOR', R3Locale::convert2PHP($this->auth->getConfigValue('GENERAL', 'ELECTRICITY_KWH_FACTOR', 1)));
        $body = R3Help::getHelpPartFromSection($request['section'], $request['id'], R3Locale::getLanguageCode());
        return array('data' => $body !== null ? $body : '');
    }

    /**
     * Return the fraction, street and catastral municipality at one time
     * @param array $request    the request
     * @return array            the result data
     */
    public function fetch_fr_st_cm($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        if (isset($request['mu_name'])) {
            $sql = "SELECT mu_id FROM municipality WHERE mu_name_$lang ILIKE " . $db->quote($request['mu_name']);
            $mu_id = (int) $db->query($sql)->fetchColumn();
        } else {
            $mu_id = $request['mu_id'];
        }
        return array('status' => 0,
            'data' => array('mu_id_selected' => $mu_id,
                'fr_id' => array('options' => R3EcoGisHelper::forceJSonArray(R3EcogisHelper::getFractionList($this->do_id, $mu_id, array('allow_empty' => true)))),
                'st_id' => array('options' => R3EcoGisHelper::forceJSonArray(R3EcogisHelper::getStreetList($this->do_id, $mu_id, array('allow_empty' => true)))),
                'cm_id' => array('options' => R3EcoGisHelper::forceJSonArray(R3EcogisHelper::getCatMunicList($this->do_id, $mu_id, array('allow_empty' => true))))));
    }

    /**
     * Return the fraction list
     * @param array $request    the request
     * @return array            the result data
     */
    public function fetch_fraction($request) {
        //R3Security::checkMunicipality($request['mu_id']);
        $like = isset($request['term']) ? $request['term'] : null;
        $limit = isset($request['limit']) ? $request['limit'] : null;
        $used_by = isset($request['used_by']) ? $request['used_by'] : null;
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(R3EcogisHelper::getFractionList($this->do_id, $request['mu_id'], array('like' => $like, 'limit' => $limit, 'used_by' => $used_by, 'allow_empty' => true))));
    }

    public function getStreetList($request) {
        $like = isset($request['term']) ? $request['term'] : null;
        $limit = isset($request['limit']) ? $request['limit'] : null;
        $used_by = isset($request['used_by']) ? $request['used_by'] : null;
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(R3EcogisHelper::getStreetList($this->do_id, $request['mu_id'], array('like' => $like, 'limit' => $limit, 'used_by' => $used_by, 'allow_empty' => true))));
    }

    public function fetch_catmunic($request) {
        $like = isset($request['term']) ? $request['term'] : null;
        $limit = isset($request['limit']) ? $request['limit'] : null;
        $used_by = isset($request['used_by']) ? $request['used_by'] : null;
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(R3EcogisHelper::getCatMunicList($this->do_id, $request['mu_id'], array('like' => $like, 'limit' => $limit, 'used_by' => $used_by, 'allow_empty' => true))));
    }

    public function fetch_municipality($request) {
        $like = isset($request['term']) ? $request['term'] : null;
        $limit = isset($request['limit']) ? $request['limit'] : null;
        return R3EcoGisHelper::getMunicipalityList($this->do_id, $like, $limit);
    }

    public function fetch_eneryClassLimit($request) {
        return R3EcogisHelper::getEnergyClassLimitList($request['ez_id'], $request['ec_id'], $this->do_id, array('allow_empty' => true));
    }

    public function askDelBuilding($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = forceInteger($request['id'], 0, false, '.');
        $name = $db->query("SELECT bu_name_$lang FROM building WHERE bu_id={$id}")->fetchColumn();
        $hasEnergyMeter = R3EcoGisHelper::hasEnergyMeter('BUILDING', $id);
        $hasDocument = R3EcoGisHelper::hasDocument('BUILDING', $id);
        if (!$hasEnergyMeter && !$hasDocument && $this->tryDeleteData($id)) {
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf(_("Sei sicuro di voler cancellare l'edificio \"%s\"?"), $name));
        } else {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _('Impossibile cancellare questo edificio poichè vi sono dei dati ad esso legati'));
        }
    }

    public function exportBuilding($request) {
        $validFilters = array('do_id', 'mu_id', 'mu_name', 'fr_id', 'fr_name', 'st_id', 'st_name', 'bu_civic',
                              'bu_code', 'bu_name', 'bpu_id', 'bt_id', 'bby_id', 'bry_id', 'bu_to_check');
        
        $console = R3_APP_ROOT . 'bin/console';
        $langCode = R3Locale::getLanguageCode();
        $domain = $this->auth->getDomainName();


        $token = date('YmdHis') . '-' . md5(time());
        $zip = '';
        $zipPrefix = '';
        if ($request['format'] == 'shp') {
            $format = 'shp';
            $outFormat = 'zip';
            $zip = '--zip --zip-prefix=export_building';
        } else {
            $outFormat = $format = 'xlsx';
        }
        $outputFileName = R3_TMP_DIR . "export_building_{$token}.{$format}";
        $outputLogFileName = "{$outputFileName}.log";

        $outputFileName = escapeshellarg($outputFileName);
        $outputLogFileName = escapeshellarg($outputLogFileName);

        $langCode = escapeshellarg($langCode);

        // Sanitaryze filters
        $filters = array();
        $txtFilter = '';
        if (!empty($request['filter'])) {
            foreach($request['filter'] as $key=>$val) {
                if (in_array($key, $validFilters) && !empty($val)) {
                    $filters[$key] = $val;
                }
            }
            if (isset($filters['bu_to_check']) && $filters['bu_to_check']=='F') {
                unset($filters['bu_to_check']);
            }
        }
        if (count($filters) > 0) {
            $filters = json_encode($filters);
            $txtFilter = "--json-filter " . escapeshellarg($filters);
        }

        $cmd = "php {$console} ecogis:export-buildings --domain {$domain} --lang {$langCode} " .
               " --format={$format} --output {$outputFileName} {$txtFilter} {$zip}> {$outputLogFileName} 2>&1 &";
        exec($cmd, $dummy, $retVal);
        if ($retVal == 0) {
            $url = "getfile.php?type=tmp&file=export_building_{$token}.{$outFormat}&disposition=download&name=EXPORT_BUILDING_" . date('Y-m-d') . '.' . $outFormat;
            return array(
                'status' => R3_AJAX_NO_ERROR,
                'token' => $token,
                'format' => $outFormat,
                'url' => $url);
        } else {
            throw new \Exception("Error #{$retVal} exporting file");
        }
    }

    public function getExportBuildingStatus($request) {
        
        $token = basename($request['token']);
        $format = basename($request['format']);

        $fileName = R3_TMP_DIR . "export_building_{$token}.{$format}" ;
        $lockFileName = "{$fileName}.lock";
        $logFileName = "{$fileName}.log";

        $isError = false;
        if(file_exists($fileName)) {
            $data = array('done'=>true);
            if (file_exists($logFileName)) {
                unlink($logFileName);
            }
        } else {
            $data = array(
                'done'=>false);
            if (($fp = @fopen($lockFileName, "r+"))) {
                if (flock($fp, LOCK_EX | LOCK_NB)) {
                    $isError = true;
                    flock($fp, LOCK_UN); // unlock
                }
                fclose($fp);
            } else {
                $isError = true;
            }
        }

        if ($isError) {
            return array('status' => R3_AJAX_ERROR);
        } else {
            return array(
                'status' => R3_AJAX_NO_ERROR,
                'data' => $data);
        }
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        if (!in_array($this->act, array('list', 'add'))) {
            R3Security::checkBuilding($this->id);
        }
    }

    public function getFeatureType() {
        return 'g_building.building';
    }

    public function getPrimaryKey() {
        return 'bu_id';
    }

    function hasDialogMap() {
        return $this->act == 'list';  // dialog map only on list
    }

}
