<?php

class eco_about extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'paes';

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->id = initVar('id');
        $this->act = initVar('act', 'show');
        $this->do_id = PageVar('do_id', $_SESSION['do_id'], $init | $reset, false, $this->baseName);
        $this->pr_id = PageVar('pr_id', null, $init | $reset, false, $this->baseName);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName);
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'list': return _('Credits');
        }
        return '';  // Unknown title
    }

    public function getFilterValues() {
        
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $q = $db->createSelectQuery();
        $where = array();
        if (!$this->auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
            $where[] = $q->expr->eq('do_id', (int) $this->auth->getDomainID());
        } else if ($this->do_id <> '') {
            $where[] = $q->expr->eq('do_id', $db->quote((int) $this->do_id));
        }
        if ($this->mu_id <> '') {
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

        $q->select("sl_id, cus_name_{$lang} AS cus_name, mu_name_{$lang} AS mu_name, sl_full_name_{$lang} AS sl_full_name, sl_length, CASE WHEN sl_to_check='T' THEN 'X' END AS sl_to_check  ")
                ->from('street_lighting_data');
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

        if ($this->auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
            if ($this->do_id == '') {
                $this->simpleTable->addSimpleField(_('Ente'), 'cus_name', 'text', null, array('sortable' => true, 'order_fields' => 'cus_name, mu_name, sl_id'));
            }
            if ($this->mu_id == '') {
                $this->simpleTable->addSimpleField(_('Comune'), 'mu_name', 'text', null, array('sortable' => true));
            }
        } else if (count(R3EcoGisHelper::getMunicipalityList($this->auth->getDomainID())) > 1) {
            if ($this->mu_id == '') {
                $this->simpleTable->addSimpleField(_('Comune'), 'mu_name', 'text', null, array('sortable' => true));
            }
        }
        $this->simpleTable->addSimpleField(_('Tratto'), 'sl_full_name', 'text', null, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Lunghezza'), 'sl_length', 'number', 80, array('sortable' => true, 'align' => 'center'));
        $this->simpleTable->addSimpleField(_('Da controllare'), 'sl_to_check', 'text', 80, array('sortable' => true, 'align' => 'center'));
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 85);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order);
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['sl_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'show':
                        $links[] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:showObject('$id')", "", "../images/ico_" . $act . ".gif");
                        break;
                    case 'mod':
                        $links[] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "../images/ico_" . $act . ".gif");
                        break;
                    case 'del':
                        $links[] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelStreetLighting('$id')", "", "../images/ico_" . $act . ".gif");
                        break;
                }
            }
        }
        return $links;
    }

    /**
     * Return the data for a single customer 
     */
    public function getData($id = null) {
        return;
        $lang = R3Locale::getLanguageID();
        if ($id === null) {
            $id = $this->id;
        }
        if ($this->auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
            $do_id = $_SESSION['do_id'];
        } else {
            $do_id = $this->auth->getDomainID();
        }
        $db = ezcDbInstance::get();
        $vlu = array();
        if ($this->act <> 'add') {
            if ($id === null) {
                throw new Exception("Missing ID for customer");
            }
            $q = $db->createSelectQuery();
            $where = array();
            $where[] = 'sl_id=' . (int) $id;
            if (!$this->auth->hasPerm('SHOW', 'ALL_DOMAINS')) {
                $where[] = $q->expr->eq('do_id', (int) $this->auth->getDomainID());
            }
            $q->select('*')
                    ->from('street_lighting_data')
                    ->where($where);
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $vlu['cus_name'] = $vlu['cus_name_' . $lang];
            $vlu['mu_name'] = $vlu['mu_name_' . $lang];
        } else {
            $vlu = array();  // Default value
            $vlu['do_id'] = $_SESSION['do_id'];
            $vlu['cus_name'] = R3EcoGisHelper::getDomainName($_SESSION['do_id']);
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
        
    }

    public function getPageVars() {
        $tabMode = 'ajax';
        $tabMode = 'iframe';

        $tabs = array();
        $tabs[] = array('id' => 'consumption', 'label' => _('Consumi energia'), 'url' => "list.php?on=street_lighting_consumption&sl_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode&init");
        $tabs[] = array('id' => 'document', 'label' => _('Documenti'), 'url' => "cclist.php?on=street_lighting_consumption&sl_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode&init");

        return array('tabs' => $tabs,
            'tab_mode' => $tabMode,
            'date_format' => R3Locale::getJQueryDateFormat());
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
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

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
