<?php

/**
 * Utility function class for EcoGIS
 */
class eco_public_user extends R3AppBaseObject {

    protected $fields;

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $this->fields = array();
        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->id = initVar('id');
        $this->act = 'list';
        $this->order = PageVar('order', '1A', $init, false, $this->baseName);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName);
        $this->do_id = $_SESSION['do_id'];
        $storeVar = isset($_GET['act']) && $_GET['act'] == 'list' || $reset || $init;
        if ($init || $reset) {
            $storeVar = true;
        }

        $this->us_name_email = PageVar('us_name_email', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->us_status = PageVar('us_status', null, $init | $reset, false, $this->baseName, $storeVar);

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('askEnablePublicUser');
        $this->registerAjaxFunction('askDelPublicUser');
        $this->registerAjaxFunction('enablePublicUser');
        $this->registerAjaxFunction('delPublicUser');
    }

    public function getPageTitle() {

        return _('Elenco utenti pubblici');
    }

    public function getListSQL() {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $q = $db->createSelectQuery();
        $where = array("do_id={$this->do_id}");

        if ($this->us_status <> '') {
            $where[] = "us_status=" . $db->quote($this->us_status);
        }
        if ($this->us_name_email <> '') {
            $where[] = "(us_name ILIKE " . $db->quote("%{$this->us_name_email}%") . " OR us_login ILIKE " . $db->quote("%{$this->us_name_email}%") . ")";
        }
        $q->select("*")
                ->from('ecogis.public_users');
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

        $lang = R3Locale::getLanguageID();
        $this->simpleTable->addSimpleField(_('Nome utente'), 'us_name', 'text', null, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Email/login'), 'us_login', 'text', null, array('sortable' => true));
        if ($this->auth->getConfigValue('PUBLIC_SITE', 'REGISTRATION_NEED_OPERATOR_CONFIRM', 'F') == 'T') {
            $this->simpleTable->addSimpleField(_('Stato'), 'us_status_text', 'text', 150, array('sortable' => true));
        }
        $this->simpleTable->addSimpleField(_('Data registrazione'), 'us_ins_date', 'date', 80, array('sortable' => true, 'align' => 'center'));
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 50);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order);
    }

    public function getFilterValues() {
        $filters = array();

        $filters['do_values'] = R3EcoGisHelper::getDomainList();
        $filters['us_name_email'] = $this->us_name_email;
        if ($this->auth->getConfigValue('PUBLIC_SITE', 'REGISTRATION_NEED_OPERATOR_CONFIRM', 'F') == 'T') {
            $filters['us_status_list'] = array('E' => _('Attivo'), 'D' => _('In attesa di attivazione'));
        }

        $filters['us_status'] = $this->us_status;

        return $filters;
    }

    public function getListTableRowOperations(&$row) {

        if ($row['us_status'] == 'E') {
            $row['us_status_text'] = _('Attivo');
        } else if ($row['us_status'] == 'D') {
            $row['us_status_text'] = _('In attesa di attivazione');
        } else {
            $row['us_status_text'] = $row['us_status'];
        }

        $id = $row['us_id'];
        $links = array();
        $objName = strToUpper($this->baseName);
        foreach (array('mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'mod':
                        if ($row['us_status'] == 'E') {
                            $links['MOD'] = $this->simpleTable->AddLinkCell('', '', '', "../images/ico_spacer.gif");
                        } else {
                            $links['MOD'] = $this->simpleTable->AddLinkCell(_('Attiva'), "javascript:askEnablePublicUser('{$id}')", "", "../images/ico_enable_public_user.gif");
                        }
                        break;
                    case 'del':
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelPublicUser('{$id}')", "", "../images/ico_" . $act . ".gif");
                        break;
                }
            }
        }
        return $links;
    }

    public function getListTableRowStyle(&$row) {
        if ($row['us_status'] <> 'E') {
            return array('normal' => 'imported_row');
        }
        return array();
    }

    public function getData($id = null) {
        
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getAvailableMunicipalityForCollection($request) {
        die('a');
        return array('data' => R3EcoGisMunicipalityCollectionHelper::getAvailableMunicipalityList((int) $request['do_id_collection'], array('mu_name' => $request['mu_name'])));
    }

    public function askEnablePublicUser($request) {
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => sprintf(_("Abilitare l'utente pubblico selezionato? All'utente verrà inviata una mail con i propri dati di accesso. Proseguire?")));
    }

    public function askDelPublicUser($request) {
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => sprintf(_("Sei sicuro di volere eliminare l'utente pubblico selezionato?")));
    }

    public function delPublicUser($request) {
        if ($this->auth->hasPerm('DEL', 'PUBLIC_USER')) {
            $db = ezcDbInstance::get();
            $id = (int) $request['id'];
            $db->exec("DELETE FROM ecogis.public_users WHERE us_id={$id}");
            return array('status' => R3_AJAX_NO_ERROR);
        }
    }

    public function enablePublicUser($request) {
        require_once R3_LIB_DIR . 'eco_pub_auth.php';
        require_once R3_LIB_DIR . 'eco_stat_utils.php';

        if ($this->auth->hasPerm('MOD', 'PUBLIC_USER')) {
            $db = ezcDbInstance::get();
            $id = (int) $request['id'];
            $data = $db->query("SELECT * FROM ecogis.public_users WHERE us_id={$id}")->fetch(PDO::FETCH_ASSOC);
            $data['login_url'] = R3EcoGisStatHelper::getLoginURL($this->do_id, $data['us_login'], true);
            EcoPublicUser::register($this->do_id, $data, true, $id);  // set password and change user status

            return array('status' => R3_AJAX_NO_ERROR);
        }
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
