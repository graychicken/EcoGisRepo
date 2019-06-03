<?php

class eco_global_plain extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'global_plain';

    /**
     * ecogis.global_plain fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'gp_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'mu_id', 'type' => 'lookup', 'required' => true, 'label' => _('Comune'), 'lookup' => array('table' => 'municipality')),
            array('name' => 'gp_name_1', 'type' => 'text', 'required' => true, 'label' => _('Nome')),
            array('name' => 'gp_name_2', 'type' => 'text', 'label' => _('Nome')),
            array('name' => 'gp_approval_date', 'type' => 'date', 'label' => _('Data approvazione')),
            array('name' => 'gp_approving_authority_1', 'type' => 'text', 'label' => _('Ente approvatore')),
            array('name' => 'gp_approving_authority_2', 'type' => 'text', 'label' => _('Ente approvatore')),
            array('name' => 'gp_url_1', 'type' => 'text', 'label' => _('url')),
            array('name' => 'gp_url_2', 'type' => 'text', 'label' => _('url')),
        );
        return $fields;
    }

    /**
     * Return the fields for the list table
     */
    public function getTableColumnConfig() {
        $showMunicipality = R3EcoGisHelper::getMunicipalityCount($this->do_id) > 1;
        $rows = array('mu_name' => array('label' => R3EcoGisHelper::geti18nMunicipalityLabel($this->do_id), 'visible' => $showMunicipality, 'options' => array('order_fields' => 'mu_type, mu_name')),
            'gp_name' => array('label' => _('Nome')),
            'gp_approval_date' => array('label' => _('Data approvazione'), 'type' => 'date', 'width' => 120),
            'gp_approving_authority' => array('label' => _('Ente approvatore')),
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
        $this->act = initVar('act', 'list');
        $this->do_id = $_SESSION['do_id'];  // PageVar('do_id',          $_SESSION['do_id'],    $init | $reset, false, $this->baseName);
        $this->pr_id = PageVar('pr_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->gp_name = PageVar('gp_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->order = PageVar('order', '1A', $init, false, $this->baseName, $storeVar);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName, $storeVar);

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));
        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('askDelGlobalPlain');
        $this->registerAjaxFunction('submitFormData');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo piano di azione');
            case 'mod': return _('Modifica piano di azione');
            case 'show': return _('Visualizza piano di azione');
            case 'list': return _('Elenco piani di azione');
        }
        return '';  // Unknown title
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
        $filters['do_id'] = $this->do_id;
        $filters['pr_id'] = $this->pr_id;
        $filters['mu_id'] = $this->mu_id;
        $filters['mu_name'] = $this->mu_name;
        $filters['gp_name'] = $this->gp_name;
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
        if ($this->gp_name <> '') {
            $where[] = "(gp_name_1 ILIKE " . $db->quote("%{$this->gp_name}%") . " OR gp_name_2 ILIKE " . $db->quote("%{$this->gp_name}%") . ")";
        }
        $q->select("gp_id, mu_name_{$lang} AS mu_name, gp_name_{$lang} AS gp_name, gp_approving_authority_{$lang} AS gp_approving_authority, gp_approval_date, gst_id")
                ->from('global_plain_data');
        if (count($where) > 0) {
            $q->where($where);
        }
        return $q;
    }

    public function getListTableRowStyle(&$row) {
        $style = array();
        if ($row['gst_id'] <> '') {
            $style[] = 'grid_has_exp_date';
        }
        if ($row['gp_id'] == $this->last_id) {
            $style[] = 'selected_row';
        }
        if (count($style) > 0) {
            return array('normal' => implode(' ', $style));
        }
        return array();
    }

    public function getTableLegend() {
        $result[] = array('text' => _("Piano d'azione utilizzato nel Patto dei Sindaci"), 'className' => 'grid_has_exp_date');
        return $result;
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
        $id = $row['gp_id'];
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
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelGlobalPlain('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                }
            }
        }
        return $links;
    }

    // Corregge un url.
    // Se $fromDB è true e url è vuoto, verrà restituito http://
    // Se $fromDB è false e url è http:// verrà null
    static public function adjURL($url, $fromDB = false) {
        $url = trim($url);
        if ($fromDB && $url == '') {
            return 'http://';
        } else if (!$fromDB && ($url == 'http://' || $url == 'https://')) {
            return null;
        }
        return $url;
    }

    static public function isValidURL($url) {
        $data = @parse_url($url);
        if ($data === false)
            return false;
        if (!@in_array($data['scheme'], array('http', 'https')))
            return false;
        return @strpos($data['host'], '.') > 0;
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
                    ->from('global_plain_data')
                    ->where("gp_id={$this->id}");
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $vlu['mu_name'] = $vlu["mu_name_{$lang}"];  // Autocomplete
            $vlu['gp_url_1'] = $this->adjURL($vlu["gp_url_1"], true);
            if (isset($vlu["gp_url_2"])) {
                $vlu['gp_url_2'] = $this->adjURL($vlu["gp_url_2"], true);
            }
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('global_plain', $vlu['gp_id']));
        } else {
            $vlu = array();
            $vlu['do_id'] = $this->do_id;
            $mu_values = R3EcoGisHelper::getMunicipalityList($this->do_id);
            if (count($mu_values) == 1) {
                $vlu['mu_id'] = key($mu_values);
            } else if ($this->auth->getParam('mu_id') > 0) {
                $vlu['mu_id'] = $this->auth->getParam('mu_id');
            }
            if (isset($vlu['mu_id']) && $vlu['mu_id'] <> '') {
                $vlu['mu_name'] = R3EcoGisHelper::getMunicipalityName($vlu['mu_id']);
            }
            $vlu['gp_url_1'] = $this->adjURL('', true);
            $vlu['gp_url_2'] = $this->adjURL('', true);
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
        return $lkp;
    }

    public function getPageVars() {
        $tabMode = TAB_MODE;

        $tabs = array();
        if ($this->act == 'mod' || $this->act == 'show') {
            $tabs[] = array('id' => 'global_plain_table', 'label' => _('Piano di azione'), 'url' => "edit.php?on=global_plain_table&gp_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode");
            $tabs[] = array('id' => 'doc', 'label' => _('Documenti'), 'url' => "list.php?on=document&type=global_plain&doc_object_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode");
        }
        return array('session_id' => session_id(),
            'tollerance' => '20%',
            'tabs' => $tabs,
            'tab_mode' => $tabMode,
            'date_format' => R3Locale::getJQueryDateFormat());
    }

    public function getJSVars() {
        return array('txtAskDeleteMeterRow' => _('Sei sicuro di voler eliminare questa riga?'));
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();
        $db = ezcDbInstance::get();
        $request['gp_id'] = $request['id'];
        if ($this->act <> 'del') {
            if (isset($request['mu_name']))
                $request['mu_id'] = R3EcoGisHelper::getMunicipalityIdByName($this->do_id, $request['mu_name'], $this->auth->getParam('mu_id'));
            $request['gp_url_1'] = $this->adjURL($request["gp_url_1"]);
            if (isset($request["gp_url_2"])) {
                $request['gp_url_2'] = $this->adjURL($request["gp_url_2"]);
            }
            $errors = $this->checkFormData($request);
            if (isset($request['mu_name']) && $request['mu_name'] <> '' & $request['mu_id'] == '')
                $errors['mu_name'] = array('CUSTOM_ERROR' => _('Il comune immesso non è stato trovato'));
            if ($request['gp_url_1'] != '' && !$this->isValidURL($request['gp_url_1']))
                $errors['gp_url_1'] = array('CUSTOM_ERROR' => _("L'indirizzo immesso non è valido"));
            if (isset($request["gp_url_2"]) && $request['gp_url_2'] != '' && !$this->isValidURL($request['gp_url_2']))
                $errors['gp_url_2'] = array('CUSTOM_ERROR' => _("L'indirizzo immesso non è valido"));
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $this->act == 'add' ? R3Security::checkMunicipality($request['mu_id']) : R3Security::checkGlobalPlain(@$request['id']);
            if ($this->act == 'del') {
                $sql = "DELETE FROM global_plain_row WHERE gp_id={$request['gp_id']}";
                $db->exec($sql);
                $sql = "DELETE FROM global_plain_sum WHERE gp_id={$request['gp_id']}";
                $db->exec($sql);
            }
            $id = $this->applyData($request);
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneGlobalPlain($id)");
        }
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

    public function askDelGlobalPlain($request) {
        $db = ezcDbInstance::get();
        $id = (int) @$request['id'];
        R3Security::checkGlobalPlain($id);

        $lang = R3Locale::getLanguageID();
        $name = $db->query("SELECT gp_name_$lang AS gp_name FROM global_plain_data WHERE gp_id=" . (int) $request['id'])->fetchColumn();
        if (R3EcoGisHelper::hasGlobalPlainRowHasGauge($id)) {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _("Impossibile cancellare questo piano d'azione, poichè vi sono definiti degli indicatori ad esso legati"));
        }

        if (R3EcoGisHelper::hasDocument('GLOBAL_PLAIN', $id)) {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _("Impossibile cancellare questo piano d'azione, poichè vi sono dei documenti ad esso legati"));
        }
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => sprintf(_("Sei sicuro di voler cancellare il piano di azione \"%s\"?"), $name));
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
        if (!in_array($this->act, array('list', 'add'))) {
            R3Security::checkGlobalPlain($this->id);
        }
    }

}
