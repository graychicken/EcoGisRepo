<?php

class eco_global_plain_gauge extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'global_plain_gauge';

    /**
     * ecogis.action_catalog fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'gpg_id', 'type' => 'integer', 'label' => _('PK'), 'is_primary_key' => true),
            array('name' => 'gpr_id', 'type' => 'lookup', 'required' => true, 'lookup' => array('table' => 'global_plain_row')),
            array('name' => 'gpg_name_1', 'type' => 'text', 'required' => true, 'label' => _('Nome')),
            array('name' => 'gpg_name_2', 'type' => 'text', 'label' => _('Nome 2')),
            array('name' => 'gpgu_id_1', 'type' => 'lookup', 'required' => false, 'lookup' => array('table' => 'global_plain_gauge_udm', 'field' => 'gpgu_id')),
            array('name' => 'gpgu_id_2', 'type' => 'lookup', 'required' => false, 'lookup' => array('table' => 'global_plain_gauge_udm', 'field' => 'gpgu_id')),
            array('name' => 'gpg_value_1', 'type' => 'float', 'required' => true, 'label' => _('Valore unitario A')),
            array('name' => 'gpg_value_2', 'type' => 'float', 'required' => true, 'label' => _('Valore unitario B')),
            array('name' => 'gpg_value_3', 'type' => 'float', 'required' => true, 'label' => _('Valore unitario C')),
            array('name' => 'gpg_is_production', 'type' => 'boolean', 'required' => true, 'label' => _('Tipo indicatore')),
        );
        return $fields;
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
        $this->gpr_id = initVar('gpr_id');

        $this->order = PageVar('order', '1A', $init, false, $this->baseName, $storeVar);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName, $storeVar);

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('askDelGlobalPlainGauge');
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
     * Return the sql to generate the list
     */
    public function getListSQL() {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $q = $db->createSelectQuery();
        $where = array();
        $where[] = $q->expr->eq('gpr_id', $this->gpr_id);

        $reduction = $db->quote(_('Riduzione energetica'));
        $production = $db->quote(_('Produzione energetica'));
        $q->select("gpg_id, gpg_name_{$lang} AS gpg_name, " .
                        "gpgu_1.gpgu_name_{$lang} AS gpgu_name_1, gpg_value_1, " .
                        "gpgu_2.gpgu_name_{$lang} AS gpgu_name_2, gpg_value_2, gpg_value_3, " .
                        "CASE gpg_is_production WHEN TRUE THEN {$production} ELSE {$reduction} END AS gpg_is_production_text")
                ->from('ecogis.global_plain_gauge gpg')
                ->leftJoin('ecogis.global_plain_gauge_udm gpgu_1', 'gpg.gpgu_id_1=gpgu_1.gpgu_id')
                ->leftJoin('ecogis.global_plain_gauge_udm gpgu_2', 'gpg.gpgu_id_2=gpgu_2.gpgu_id')
                ->where($where);
        return $q;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {
        $hasReductionAndProduction = count(R3EcoGisHelper::getGlobalPlainGaugeTypeList($_SESSION['do_id'], $this->gpr_id)) > 1;

        $this->simpleTable->addSimpleField(_('Descrizione'), 'gpg_name', 'text', null, array('sortable' => false));
        $this->simpleTable->addSimpleField(_('Valore unitario A'), 'gpg_value_1', 'number', null, array('sortable' => false));  // 0.031578 adimensionale
        $this->simpleTable->addSimpleField(_('Valore unitario B'), 'gpg_value_2', 'number', null, array('sortable' => false));  // 1.33 
        $this->simpleTable->addSimpleField(_('Fattore emissione'), 'gpg_value_3', 'number', null, array('sortable' => false));  // 0.202
        $this->simpleTable->addSimpleField(_('U.d.m. quantità'), 'gpgu_name_1', 'text', null, array('sortable' => false));
        $this->simpleTable->addSimpleField(_('U.d.m. efficienza'), 'gpgu_name_2', 'text', null, array('sortable' => false));
        if ($hasReductionAndProduction) {
            $this->simpleTable->addSimpleField(_('Tipo indicatore'), 'gpg_is_production_text', 'text', null, array('sortable' => false));
        }
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 85);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order);
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['gpg_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;

        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'show':
                        $links[] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:showGlobalPlainGauge({$row['gpg_id']})", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modGlobalPlainGauge({$row['gpg_id']})", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'del':
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelGlobalPlainGauge({$row['gpg_id']})", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                }
            }
        }
        return $links;
    }

    public function getListTableRowStyle(&$row) {
        if ($row['gpg_id'] == $this->last_id) {
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
            $q->select("*, CASE WHEN gpg_is_production IS TRUE THEN 'T' ELSE 'F' END AS gpg_is_production")
                    ->from('ecogis.global_plain_gauge')
                    ->where("gpg_id={$this->id}");
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $this->gpr_id = $vlu['gpr_id'];
        } else {
            $vlu = array();
            $vlu['gpr_id'] = $this->gpr_id;
        }
        return $vlu;
    }

    public function forceGaugeTypeToGauge($gpr_id) {
        $db = ezcDbInstance::get();

        $sql = "UPDATE ecogis.global_plain_row SET gpr_gauge_type='G' WHERE gpr_id=:gpr_id AND COALESCE(gpr_gauge_type, '')<>'G'";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('gpr_id' => $gpr_id));
    }

    public function submitFormData($request) {
        $db = ezcDbInstance::get();

        $errors = array();
        $request['gpg_id'] = forceInteger($request['id'], 0, false, '.');
        if ($this->act <> 'del') {
            $errors = $this->checkFormData($request, $errors);
        }

        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $db->beginTransaction();
            if ($this->act == 'add') {
                $this->forceGaugeTypeToGauge($request['gpr_id']);  // Modifica record parent
            }
            $id = $this->applyData($request);
            $db->commit();
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneGlobalPlainGauge({$id})");
        }
    }

    public function getLookupData($id = null) {

        $lkp = array();
        $lkp['gpgu_values'] = R3EcoGisHelper::getGlobalPlainGaugeUdmList($_SESSION['do_id']);
        $lkp['gpg_is_production_values'] = R3EcoGisHelper::getGlobalPlainGaugeTypeList($_SESSION['do_id'], $this->gpr_id);
        return $lkp;
    }

    public function getPageVars() {
        $hasReductionOrPruduction = count(R3EcoGisHelper::getGlobalPlainGaugeTypeList($_SESSION['do_id'], $this->gpr_id)) > 0;
        return array('hasReductionOrPruduction' => $hasReductionOrPruduction,
            'gpr_id' => $this->gpr_id,
            'tab_mode' => $this->tab_mode,
            'parent_act' => $this->parent_act,
            'date_format' => R3Locale::getJQueryDateFormat());
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        return array('txtCantAdd' => _("Impossibile aggiungere indicatori ad un'azione senza rispermio o produzione energetica"),
            'txtAddGlobalPlainGauge' => _('Aggiungi indicatore'),
            'txtShowGlobalPlainGauge' => _('Mostra indicatore'),
            'txtModGlobalPlainGauge' => _('Modifica indicatore'),
        );
    }

    public function askDelGlobalPlainGauge($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = forceInteger($request['id'], 0, false, '.');
        $name = $db->query("SELECT gpg_name_$lang FROM ecogis.global_plain_gauge WHERE gpg_id={$id}")->fetchColumn();
        if ($this->tryDeleteData($id)) {
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf(_("Sei sicuro di voler cancellare l'indicatore \"%s\"?"), $name));
        } else {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _('Impossibile cancellare questa voce dal catalogo azioni poichè vi sono dei dati ad esso legati'));
        }



        $name = $db->query("SELECT ac_name_$lang FROM action_catalog WHERE ac_id={$id}")->fetchColumn();
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
