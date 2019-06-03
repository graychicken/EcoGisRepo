<?php

require_once R3_LIB_DIR . 'r3export_paes.php';

class R3ExportLogger implements R3ExportPAESLogger {

    private $totSteps;
    private $curSteps;

    private $logFileName;

    function __construct($logFileName) {
        $this->logFileName = $logFileName;
    }

    public function log($level, $text) {
        // echo "[$level|$text]\n";
        ezcLog::getInstance()->log(sprintf("Export PAES: %s", $text), ezcLog::DEBUG);
    }

    public function initProgress($totSteps) {
        $this->totSteps = $totSteps;
        $this->curSteps = 0;
    }

    public function step($kind, $table, $tableNo) {
        $this->curSteps++;
        if ($this->totSteps > 0) {
            $perc = round(min(100, $this->curSteps / $this->totSteps * 100), 1);
        } else {
            $perc = 0;
        }
        switch ($table) {
            case '': break;
            case 'CONSUMPTION': $table = _('Consumo energetico');
                break;
            case 'EMISSION': $table = _('Emissioni di CO2');
                break;
            case 'ENERGY_PRODUCTION': $table = _('Produzione locale di elettricità');
                break;
            case 'HEATH_PRODUCTION': $table = _('Produzione locale di calore/freddo');
                break;
            default: throw new exception("Unkonwn table {$table}");
        }
        switch ($kind) {
            case R3_PAES_PREPARE_DATA: $text = _('Preparazione dati...');
                break;
            case R3_PAES_READ_TEMPLATE: $text = _('Lettura template...');
                break;
            case R3_PAES_READ_CONFIG: $text = _('Lettura configurazione...');
                break;
            case R3_PAES_REPLACE: $text = _('Sostituzioni...');
                break;
            case R3_PAES_EMISSION_TABLE: $text = _("Generazione tabella \"{$table}\"...");
                break;
            case R3_PAES_ACTION_PLAN_TABLE: $text = _("Generazione tabella \"Piano di azione\"...");
                break;
            case R3_PAES_FINALYZE: $text = _('Finalizzazione...');
                break;
            case R3_PAES_SAVE: $text = _('Generazione Excel finale...');
                break;
            case R3_PAES_DONE: $text = _('Fine');
                break;
            default: throw new exception("Unkonwn kind {$kind}");
        }

        file_put_contents($this->logFileName, serialize(array('progress' => $perc, 'text' => $text)));

        //@session_start();  // Dont show session error (debug pourpose)
        //$_SESSION['PAES_EXPORT_STATUS'] = array('progress' => $perc, 'text' => $text);
        //session_write_close();
    }

}

class R3EcoGisGlobalStrategyHelper {

    static public function getGlobalEntryList($do_id, $mu_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $constraints = array('do_id=' . (int) $do_id, 'mu_id=' . (int) $mu_id);
        $opt = array_merge(array('constraints' => $constraints), $opt);
        return R3Opt::getOptList('global_entry_data', 'ge_id', 'ge_name_' . R3Locale::getLanguageID() . '_year', $opt);
    }

    static public function getGlobalPlainList($do_id, $mu_id, array $opt = array()) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $constraints = array('do_id=' . (int) $do_id, 'mu_id=' . (int) $mu_id);
        $opt = array_merge(array('constraints' => $constraints), $opt);
        return R3Opt::getOptList('global_plain_data', 'gp_id', 'gp_name_' . R3Locale::getLanguageID(), $opt);
    }

    static public function getGlobalData($do_id, $mu_id, array $opt = array()) {
        $result = array();
        $result['ge_values'] = R3EcoGisGlobalStrategyHelper::getGlobalEntryList($_SESSION['do_id'], $mu_id, $opt);
        $result['gp_values'] = R3EcoGisGlobalStrategyHelper::getGlobalPlainList($_SESSION['do_id'], $mu_id, $opt);
        if (count($result['ge_values']) == 1 && count($result['gp_values']) == 1) {
            $result['info_text'] = _("Attenzione: Per poter aderire al Patto dei sindaci, bisogna creare e poi selezionare sia un inventario emissioni che un piano di azione");
        } else if (count($result['ge_values']) == 1) {
            $result['info_text'] = _("Attenzione: Per poter aderire al Patto dei sindaci, bisogna creare e poi selezionare almeno un inventario emissioni");
        } else if (count($result['gp_values']) == 1) {
            $result['info_text'] = _("Attenzione: Per poter aderire al Patto dei sindaci, bisogna creare e poi selezionare almeno un piano di azione");
        } else {
            $result['info_text'] = '';
        }
        return $result;
    }

    static public function getExportPAESDlgHTML($request) {
        global $smarty, $languages;

        $auth = R3AuthInstance::get();
        $driverInfo = $auth->getConfigValue('APPLICATION', 'EXPORT_PAES', array());
        foreach ($driverInfo as $key => $info) {
            $outputFormat[$key] = _($info['label']);
        }
        $lkp = array('formats' => $outputFormat);
        $vlu = array('id' => $request['id'],
            'languageId' => R3Locale::getLanguageCode());
        $smarty->assign('lkp', $lkp);
        $smarty->assign('vlu', $vlu);
        $smarty->assign('vars', array('save' => isset($request['save']) && $request['save'] == 'T' ? 'T' : 'F'));
        return $smarty->fetch('export_paes_dlg.tpl');
    }

    /**
     * Return true if the global strategy exists for the given municipality
     */
    static public function globalStrategyExists($mu_id) {
        $db = ezcDbInstance::get();
        $sql = "SELECT COUNT(*) FROM global_strategy WHERE mu_id=" . (int) $mu_id;
        return $db->query($sql)->fetchColumn() > 0;
    }

    static function getAvailableMunicipalityList($do_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $sql = "SELECT mu_id, mu_name_{$lang} AS mu_name
                    FROM ecogis.municipality_data
                    WHERE do_id={$do_id} AND 
                          mu_id NOT IN (SELECT mu_id FROM global_strategy)
                    ORDER BY mu_name_{$lang} ";
        $result = array();
        foreach ($db->query($sql) as $row) {
            $result[$row['mu_id']] = $row['mu_name'];
        }
        return $result;
    }

    static function getAvailableMunicipalityCollectionList($do_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $sql = "SELECT mu_id, mu_name_{$lang} AS mu_name
                    FROM ecogis.municipality_collection_data
                    WHERE do_id={$do_id} AND 
                          mu_id NOT IN (SELECT mu_id FROM global_strategy)
                    ORDER BY mu_name_{$lang} ";
        $result = array();
        foreach ($db->query($sql) as $row) {
            $result[$row['mu_id']] = $row['mu_name'];
        }
        return $result;
    }

    static function getAvailableMunicipalityAndMunicipalityCollectionList($do_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $munucipalityCollectionList = self::getAvailableMunicipalityCollectionList($do_id);
        if (count($munucipalityCollectionList) > 0) {
            $result['data'][_('Raggruppamenti')] = $munucipalityCollectionList;
            $result['data'][_('Comuni')] = self::getAvailableMunicipalityList($do_id);
            $result['tot']['municipality'] = count($result['data'][_('Comuni')]);
            $result['tot']['collection'] = count($result['data'][_('Raggruppamenti')]);
        } else {
            $result['data'] = self::getAvailableMunicipalityList($do_id);
            $result['tot']['municipality'] = count($result['data']);
        }
        $result['has_municipality_collection'] = R3EcoGisHelper::hasMunicipalityCollection($do_id);
        return $result;
    }

}

class eco_global_strategy extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'global_strategy';

    /**
     * Info text message
     */
    protected $info_text = '';

    /**
     * ecogis.global_strategy fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'gst_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'mu_id', 'type' => 'lookup', 'required' => true, 'label' => _('Comune'), 'lookup' => array('table' => 'municipality')),
            array('name' => 'gst_name_1', 'type' => 'text', 'required' => true, 'label' => _('Nome')),
            array('name' => 'gst_name_2', 'type' => 'text', 'required' => false, 'label' => _('Nome')),
            array('name' => 'gst_target_descr_1', 'type' => 'text'),
            array('name' => 'gst_target_descr_2', 'type' => 'text'),
            array('name' => 'gst_reduction_target', 'type' => 'float', 'required' => true, 'precision' => 1, 'label' => _('Obiettivo riduzione')),
            array('name' => 'gst_reduction_target_year', 'type' => 'year', 'required' => true, 'label' => _('Anno obiettivo riduzione')),
            array('name' => 'gst_reduction_target_citizen', 'type' => 'integer', 'required' => true, 'label' => _('Abitanti obiettivo previsto')),
            array('name' => 'gst_reduction_target_absolute', 'type' => 'boolean', 'required' => true, 'default' => 'true'),
            array('name' => 'gst_reduction_target_long_term', 'type' => 'float', 'precision' => 1),
            array('name' => 'gst_reduction_target_year_long_term', 'type' => 'year'),
            array('name' => 'gst_reduction_target_citizen_long_term', 'type' => 'integer'),
            array('name' => 'gst_reduction_target_absolute_long_term', 'type' => 'boolean', 'required' => true, 'default' => 'true'),
            array('name' => 'gst_emission_factor_type_ipcc', 'type' => 'boolean', 'required' => true, 'default' => 'true'),
            array('name' => 'gst_emission_unit_co2', 'type' => 'boolean', 'required' => true, 'default' => 'true'),
            array('name' => 'gst_coordination_text_1', 'type' => 'text'),
            array('name' => 'gst_coordination_text_2', 'type' => 'text'),
            array('name' => 'gst_staff_nr', 'type' => 'integer', 'label' => _('Personale impiegato')),
            array('name' => 'gst_staff_text_1', 'type' => 'text'),
            array('name' => 'gst_staff_text_2', 'type' => 'text'),
            array('name' => 'gst_citizen_text_1', 'type' => 'text'),
            array('name' => 'gst_citizen_text_2', 'type' => 'text'),
            array('name' => 'gst_budget', 'type' => 'float', 'precision' => 2, 'label' => _('Importo')),
            array('name' => 'gst_budget_text_1', 'type' => 'text'),
            array('name' => 'gst_budget_text_2', 'type' => 'text'),
            array('name' => 'gst_financial_text_1', 'type' => 'text'),
            array('name' => 'gst_financial_text_2', 'type' => 'text'),
            array('name' => 'gst_monitoring_text_1', 'type' => 'text'),
            array('name' => 'gst_monitoring_text_2', 'type' => 'text'),
            array('name' => 'ge_id', 'type' => 'lookup', 'lookup' => array('table' => 'global_entry')),
            array('name' => 'ge_id_2', 'type' => 'lookup', 'lookup' => array('table' => 'global_entry', 'field' => 'ge_id')),
            array('name' => 'gp_id', 'type' => 'lookup', 'lookup' => array('table' => 'global_plain')),
        );
        return $fields;
    }

    /**
     * Return the fields for the list table
     */
    public function getTableColumnConfig() {
        $showMunicipality = R3EcoGisHelper::getMunicipalityCount($this->do_id) > 1;
        $rows = array('mu_name' => array('label' => R3EcoGisHelper::geti18nMunicipalityLabel($this->do_id), 'visible' => $showMunicipality, 'options' => array('order_fields' => 'mu_type, mu_name')),
            'gst_name' => array('label' => _('Nome'), 'width' => 250),
            'gst_target_descr' => array('label' => _('Descrizione obiettivi')),
            'gst_reduction_target' => array('label' => _('Obiettivo riduzione (%)'), 'width' => 100, 'type' => 'float', 'options' => array('number_format' => array('decimals' => 2))),
            'gst_reduction_target_year' => array('label' => _('Anno riduzione'), 'visible' => false, 'width' => 100, 'type' => 'integer', 'options' => array('align' => 'right', 'order_fields' => 'bu_code_pad, bu_name, bu_id')),
            'gst_reduction_target_absolute' => array('label' => _('Riduz. assoluta'), 'width' => 60, 'options' => array('align' => 'center')),
            'gst_staff_nr' => array('label' => _('Personale impiegato'), 'visible' => false, 'width' => 100, 'type' => 'integer', 'options' => array('number_format' => array('decimals' => null))),
            'gst_budget' => array('label' => _("Stima risorse (€)"), 'type' => 'integer', 'width' => 100, 'options' => array('number_format' => array('decimals' => 2))),
            'gst_emission_factor_type' => array('label' => _('Fatt.emiss.'), 'visible' => false, 'width' => 100),
            'gst_emission_unit' => array('label' => _('Tipo emiss.'), 'width' => 80),
        );
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
        $this->id = (int) initVar('id');
        $this->last_id = initVar('last_id');
        $this->act = initVar('act', 'list');
        $this->parent_act = initVar('parent_act');
        $this->do_id = PageVar('do_id', $_SESSION['do_id'], $init | $reset, false, $this->baseName, $storeVar);
        $this->pr_id = PageVar('pr_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->gst_name = PageVar('gst_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->order = PageVar('order', '1A', $init, false, $this->baseName, $storeVar);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName, $storeVar);

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('loadGE_GS');
        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('exportPAES');
        $this->registerAjaxFunction('exportPAESDlg');
        $this->registerAjaxFunction('getExportPAESStatus');
        $this->registerAjaxFunction('askDelGlobalStrategy');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Creazione parametri principali');
            case 'mod': return _('Modifica parametri principali');
            case 'show': return _('Parametri principali');
            case 'list': return _('Parametri principali');
        }
        return '';  // Unknown title
    }

    /**
     * Return the filter values (list form)
     */
    public function getFilterValues() {
        $filters = array();
        if ($this->auth->getParam('mu_id') == '') {
            $filters['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id, array('join_with_global_strategy' => true));
            $filters['mu_values'] = R3EcoGisHelper::getMunicipalityAndMunicipalityCollectionList($this->do_id, null, null, array('join_with_global_strategy' => true));
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
        $filters['gst_name'] = $this->gst_name;

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
        if ($this->gst_name <> '') {
            $where[] = "gst_name_{$lang} ILIKE " . $db->quote("%{$this->gst_name}%");
        }
        $q->select("gst_id, gst_name_{$lang} AS gst_name, mu_name_{$lang} AS mu_name, gst_target_descr_{$lang} AS gst_target_descr, gst_reduction_target, gst_reduction_target_year, gst_staff_nr,
                    gst_staff_text_1, gst_staff_text_2, gst_budget,
                    CASE WHEN gst_reduction_target_absolute IS TRUE THEN 'X' ELSE NULL END AS gst_reduction_target_absolute,
                    gst_emission_factor_type,gst_emission_unit")
                ->from('global_strategy_data');
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
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order, 'global_strategy_list_table');
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['gst_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'show':
                        $links['SHOW'] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:showObject('$id')", "", "{$this->imagePath}ico_" . $act . ".gif");
                        break;
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "{$this->imagePath}ico_" . $act . ".gif");
                        break;
                    case 'del':
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelGlobalStrategy('$id')", "", "{$this->imagePath}ico_" . $act . ".gif");
                        break;
                }
            }
        }
        return $links;
    }

    public function getListTableRowStyle(&$row) {
        if ($row['gst_id'] == $this->last_id) {
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
        if ($this->auth->getParam('mu_id') <> '') {
            // override the default action if it is a municipality user
            $sql = "SELECT gst_id FROM global_strategy WHERE mu_id=" . $db->quote($this->auth->getParam('mu_id'));
            $this->id = $db->query($sql)->fetchColumn();
            $this->act = $this->id > 0 ? 'mod' : 'add';
        }
        $vlu = array();
        if ($this->act <> 'add') {
            R3Security::checkGlobalStrategy($this->id);
            $q = $db->createSelectQuery();
            $q->select('*')
                    ->from('global_strategy_data')
                    ->where("gst_id={$this->id}");
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $vlu['mu_name'] = $vlu["mu_name_{$lang}"];  // Autocomplete
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('global_strategy', $this->id));
        } else {
            $vlu['do_id'] = $this->do_id;
            $mu_values = R3EcoGisGlobalStrategyHelper::getAvailableMunicipalityList($this->do_id);
            if (count($mu_values) == 1) {
                $vlu['mu_id'] = key($mu_values);
            } else if ($this->auth->getParam('mu_id') > 0) {
                $vlu['mu_id'] = $this->auth->getParam('mu_id');
            }
            if (isset($vlu['mu_id']) && $vlu['mu_id'] <> '') {
                $vlu['gst_name_1'] = 'Adesione al Patto dei Sindaci - Comune di ' . R3EcoGisHelper::getMunicipalityName($vlu['mu_id'], 1);
                $vlu['gst_name_2'] = 'Bürgermeisterkonvents - Gemeinde ' . R3EcoGisHelper::getMunicipalityName($vlu['mu_id'], 2);
            } else {
                $vlu['gst_name_1'] = 'Adesione al Patto dei Sindaci';
                $vlu['gst_name_2'] = 'Bürgermeisterkonvents';
            }
            $vlu['gst_reduction_target'] = 20;
            $vlu['gst_reduction_target_year'] = 2020;
            $vlu['gst_reduction_target_absolute'] = $vlu['gst_reduction_target_absolute_long_term'] = 'T';
            $vlu['gst_emission_factor_type_ipcc'] = $vlu['gst_emission_unit_co2'] = 'T';
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
            $lkp['mu_values'] = R3EcoGisGlobalStrategyHelper::getAvailableMunicipalityAndMunicipalityCollectionList($this->do_id);
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
            $data = R3EcoGisGlobalStrategyHelper::getGlobalData($_SESSION['do_id'], $mu_id, array('allow_empty' => true));
            $lkp['ge_values'] = $data['ge_values'];
            $lkp['gp_values'] = $data['gp_values'];
            $this->info_text = $data['info_text'];
        }
        $lkp['gst_emission_factor_type_ipcc_values'] = array('T' => _('IPCC'), 'F' => _('LCA'));
        $lkp['gst_emission_unit_co2_values'] = array('T' => _('CO2'), 'F' => _('Equivalenti di CO2'));
        return $lkp;
    }

    public function getPageVars() {
        $mu_values = R3EcoGisGlobalStrategyHelper::getAvailableMunicipalityAndMunicipalityCollectionList($this->do_id);
        $canAdd = (!empty($mu_values['tot']['collection']) && $mu_values['tot']['collection'] > 0) || $mu_values['tot']['municipality'] > 0;
        return array('info_text' => $this->info_text,
            'view_mode' => $this->auth->hasPerm('MOD', 'GLOBAL_STRATEGY') ? 'F' : 'T',
            'stay_to_edit' => $this->auth->getParam('mu_id') == '' ? 'F' : 'T',
            'can_add' => $canAdd,
            'parent_act' => $this->parent_act,
            'export_paes' => 'F');
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        return array('txtSaveDone' => _('Salvataggio avvenuto con successo'),
            'txtExportPAES' => _('Export PAES'));
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();
        $db = ezcDbInstance::get();
        $default = array('ge_id' => null, 'ge_id_2' => null);
        $request = array_merge($default, $request);

        if ($this->auth->getParam('mu_id') <> '') {
            // override the default action if it is a municipality user
            $sql = "SELECT gst_id FROM global_strategy WHERE mu_id=" . $db->quote($this->auth->getParam('mu_id'));
            $this->id = $db->query($sql)->fetchColumn();
            $this->act = $this->id > 0 ? 'mod' : 'add';
            $request['act'] = $this->act;
        }

        if ($this->act <> 'add') {
            R3Security::checkGlobalStrategy(@$request['id']);
        }
        $request['gst_id'] = $request['id'];
        if (isset($request['mu_name']))
            $request['mu_id'] = R3EcoGisHelper::getMunicipalityIdByName($this->do_id, $request['mu_name'], $this->auth->getParam('mu_id'));
        if ($this->act <> 'del') {
            $errors = $this->checkFormData($request);
            if (isset($request['mu_name']) && $request['mu_name'] <> '' & $request['mu_id'] == '') {
                $errors['mu_name'] = array('CUSTOM_ERROR' => _('Il comune immesso non è stato trovato'));
            }
            if ($request['gst_reduction_target'] < 20) {
                $errors['gst_reduction_target'] = array('CUSTOM_ERROR' => _("L'obiettivo previsto deve essere di almento 20%%"));
            }
            if ($request['gst_reduction_target_year'] <> 2020) {
                $errors['gst_reduction_target_year'] = array('CUSTOM_ERROR' => _("L'anno dell'obiettivo deve essere 2020"));
            }
            if ($request['gst_reduction_target_long_term'] <> '' &&
                    ($request['gst_reduction_target_year_long_term'] <> '' xor
                    $request['gst_reduction_target_citizen_long_term'] <> '')) {
                $errors['gst_reduction_target_long_term'] = array('CUSTOM_ERROR' => _('I valori "Obiettivo a lungo termine", "Anno lungo termine" e "Abitanti lungo temine", (se indicati) devono essere presenti'));
            }
            if ($request['ge_id'] <> '' && $request['ge_id_2'] <> '' && $request['ge_id'] == $request['ge_id_2']) {
                $errors['ge_id_2'] = array('CUSTOM_ERROR' => _("L'inventario e l'inventario 2 devono essere diversi"));
            }
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $id = $this->applyData($request);
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneGlobalStrategy($id)");
        }
    }

    public function askDelGlobalStrategy($request) {
        $db = ezcDbInstance::get();
        R3Security::checkGlobalStrategy(@$request['id']);
        $lang = R3Locale::getLanguageID();
        $name = $db->query("SELECT gst_name_$lang AS gst_name FROM global_strategy_data WHERE gst_id=" . (int) $request['id'])->fetchColumn();

        if ($this->tryDeleteData($request['id'])) {
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf(_("Sei sicuro di voler cancellare l'adesione al Patto dei Sindaci \"%s\"?"), $name));
        } else {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => sprintf(_("Impossibile cancellare l'adesione al Patto dei Sindaci \"%s\" poichè vi sono dei dati ad essa collegati"), $name));
        }
    }

    public function exportPAES($request) {

        $console = R3_APP_ROOT . 'bin/console';
        $id = empty($request['id']) ? (int)$this->id : (int) $request['id'];
        $id = escapeshellarg($id);

        $domain = $this->auth->getDomainName();
        $domain = escapeshellarg($domain);

        $user = $this->auth->getLogin();
        $user = escapeshellarg($user);

        $lang = R3Locale::getLanguageCode();
        $lang = escapeshellarg($lang);

        $token = date('YmdHis') . '-' . md5(time());
        $outputFileName = R3_TMP_DIR . "export_paes_{$token}.xls";
        $outputFileName = escapeshellarg($outputFileName);

        $outputLog = R3_TMP_DIR . "export_paes_{$token}.log";
        $outputLog = escapeshellarg($outputLog);
        
        $cmd = "php {$console} ecogis:export-seap --id {$id} --domain {$domain} --user {$user} --lang {$lang} --output {$outputFileName} > {$outputLog} 2>&1 &";
        exec($cmd, $dummy, $retVal);
        if ($retVal == 0) {
            $url = "getfile.php?type=tmp&file=export_paes_{$token}.xls&disposition=download&name=PAES_" . date('Y-m-d') . '.xls';
            return array(
                'status' => R3_AJAX_NO_ERROR,
                'token' => $token,
                'url' => $url);
        } else {
            throw new \Exception("Error #{$retVal} exporting file");
        }
    }

    public function getExportPAESStatus($request) {
        $token = basename($request['token']);

        $xlsFileName = R3_TMP_DIR . "export_paes_{$token}.xls" ;
        $stsFileName = "{$xlsFileName}.sts";
        $lockFileName = "{$xlsFileName}.lock";
        $logFileName = "{$xlsFileName}.lock";

        $isError = false;
        if(file_exists($xlsFileName)) {
            $data = array(
                'done'=>true,
                'progress'=>100,
                'text'=>'Done');
            if (file_exists($stsFileName)) {
                unlink($stsFileName);
            }
            if (file_exists($lockFileName)) {
                unlink($lockFileName);
            }
            if (file_exists($logFileName)) {
                unlink($logFileName);
            }
        } else {
            $data = array(
                'done'=>false,
                'progress'=>0,
                'text'=>'');
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

        for($i = 0; $i <= 2; $i++) {
            if (file_exists($stsFileName)) {
                try {
                    $data = array_merge($data, unserialize(file_get_contents($stsFileName)));
                    break;
                } catch(\Exception $e){
                    if ($i == 2) {
                        throw $e;
                    }
                    usleep(100000);
                }
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

    public function exportPAESDlg($request) {
        if (isset($_SESSION['PAES_EXPORT_STATUS'])) {
            unset($_SESSION['PAES_EXPORT_STATUS']);
        }
        return R3EcoGisGlobalStrategyHelper::getExportPAESDlgHTML($request);
    }

    public function loadGE_GS($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        if (isset($request['mu_name'])) {
            $request['mu_id'] = R3EcoGisHelper::getMunicipalityIdByName($this->do_id, $request['mu_name'], $this->auth->getParam('mu_id'));
        }
        $data = R3EcoGisGlobalStrategyHelper::getGlobalData($_SESSION['do_id'], $request['mu_id'], array('allow_empty' => true));
        return array('status' => 0,
            'data' => array('mu_id_selected' => (int) $request['mu_id'],
                'ge_id' => array('options' => $data['ge_values']),
                'ge_id_2' => array('options' => $data['ge_values']),
                'gp_id' => array('options' => $data['gp_values']),
                'info_text' => $data['info_text']));
    }

    public function checkPerm() {
        $mu_id = $this->auth->getParam('mu_id');
        $this->act = $mu_id == '' ? $this->act : 'show';

        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }

        if (!in_array($this->act, array('list', 'add'))) {
            if ($mu_id <> '' && $this->id == '') {
                // Nothing do check
            } else {
                R3Security::checkGlobalStrategy($this->id);
            }
        }
    }

}
