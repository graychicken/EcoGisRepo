<?php

/**
 * Utility function class for EcoGIS
 */
class R3EcoGisMunicipalityCollectionHelper {

    static public function getAvailableMunicipalityList($do_id, array $opt = array()) {
        $do_id = (int) $do_id;
        $opt = array_merge(array('mu_name' => ''), $opt);
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $constraints = array();
        $constraints[] = "do_id={$do_id}";
        $constraints[] = "mu_type='M'";
        $constraints[] = "mu_parent_id IS NULL";
        if ($opt['mu_name'] <> '') {
            $constraints[] = "mu_name_{$lang} ILIKE " . $db->quote('%' . $opt['mu_name'] . '%');
        }
        return R3Opt::getOptList('municipality', 'mu_id', "mu_name_{$lang}", array('constraints' => $constraints));
    }

    static public function getSelectedMunicipalityList($mu_id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $constraints = array();
        $constraints[] = 'mu_parent_id=' . (int) $mu_id;
        return R3Opt::getOptList('municipality', 'mu_id', "mu_name_{$lang}", array('constraints' => $constraints));
    }

}

class eco_municipality_collection extends R3AppBaseObject {

    protected $fields;

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $this->fields = array();
        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->id = initVar('id');
        $this->act = initVar('act', 'list');
        $this->order = PageVar('order', '1A', $init, false, $this->baseName);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName);
        $storeVar = isset($_GET['act']) && $_GET['act'] == 'list' || $reset || $init;  // if true store the filter variables
        if ($init || $reset) {
            $storeVar = true;
        }

        $this->do_id_filter = (int) PageVar('do_id_filter', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_name_collection = PageVar('mu_name_collection', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName, $storeVar);

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('confirmDeleteMunicipalityCollection');
        $this->registerAjaxFunction('getAvailableMunicipalityForCollection');
    }

    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo raggruppamento di comuni');
            case 'mod': return _('Modifica raggruppamento di comuni');
            case 'show': return _('Visualizza raggruppamento di comuni');
            case 'list': return _('Elenco raggruppamento di comuni');
        }
        return '';  // Unknown title
    }

    public function getListSQL() {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $q = $db->createSelectQuery();
        $where = array("do_id IS NOT NULL");

        if ($this->do_id_filter <> '') {
            $where[] = "do_id={$this->do_id_filter}";
        }
        if ($this->mu_name_collection <> '') {
            $where[] = "mu_name_{$lang} ILIKE " . $db->quote("%{$this->mu_name_collection}%");
        }
        if ($this->mu_name <> '') {
            $where[] = "mu_name_list_{$lang} ILIKE " . $db->quote("%{$this->mu_name}%");
        }
        $q->select("mu_id, cus_name_{$lang} AS cus_name, mu_name_{$lang} AS mu_name, tot_child, mu_name_list_{$lang} AS mu_name_list")
                ->from('ecogis.municipality_collection_data');
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
        if (R3_IS_MULTIDOMAIN) {
            $this->simpleTable->addSimpleField(_('Ente'), 'cus_name', 'text', 150, array('sortable' => true));
        }
        $this->simpleTable->addSimpleField(_('Nome raggruppamento'), 'mu_name', 'text', 200, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('# Comuni'), 'tot_child', 'integer', 50, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Comuni'), 'mu_name_list', 'text', null, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 50);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order);
    }

    public function getFilterValues() {
        $filters = array();

        $filters['do_values'] = R3EcoGisHelper::getDomainList();
        $filters['do_id_filter'] = $this->do_id_filter;
        $filters['mu_name_collection'] = $this->mu_name_collection;
        $filters['mu_name'] = $this->mu_name;

        return $filters;
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['mu_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('{$id}')", "", "../images/ico_" . $act . ".gif");
                        break;
                    case 'del':
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelMunicipalityCollection('{$id}')", "", "../images/ico_" . $act . ".gif");
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

        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        $vlu = array();
        if ($this->act == 'add') {
            
        } else {
            $sql = "SELECT * FROM ecogis.municipality_collection_data WHERE mu_id=" . $this->id;
            $vlu = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        }
        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Return the data for a single customer 
     */
    public function getLookupData($id = null) {
        $lkp = array();
        $lkp['do_values'] = R3EcoGisHelper::getDomainList();
        if (count($lkp['do_values']) == 1) {
            $lkp['mu_list'] = R3EcoGisMunicipalityCollectionHelper::getAvailableMunicipalityList(key($lkp['do_values']));
        }
        if ($this->act == 'mod') {
            $lkp['mu_selected'] = R3EcoGisMunicipalityCollectionHelper::getSelectedMunicipalityList($this->id);
        }

        return $lkp;
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
        if ($this->act == 'del') {
            $id = (int) $this->id;
            $db = ezcDbInstance::get();
            $db->beginTransaction();
            $sql = "UPDATE ecogis.municipality SET mu_parent_id=NULL WHERE mu_parent_id=:mu_parent_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(array('mu_parent_id' => $id));
            $sql = "DELETE FROM ecogis.municipality WHERE mu_id=:mu_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(array('mu_id' => $id));
            $db->commit();
            return array('status' => R3_AJAX_NO_ERROR);
        }
        if ($request['mu_name_1'] == '') {
            $errors['mu_name_1'] = array('CUSTOM_ERROR' => _("Bisogna indicare il nome del raggruppamento"));
        }
        if ($request['municipality'] == '') {
            $errors['municipality'] = array('CUSTOM_ERROR' => _("Almeno un comune deve essere selezionato"));
        }
        $request['mu_name_2'] = empty($request['mu_name_2']) ? null : $request['mu_name_2'];
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);

            return array('status' => R3_AJAX_ERROR,
                'error' => array('text' => $errText,
                    'element' => $firstId));
        }
        $idList = array();
        foreach (explode(',', $request['municipality']) as $mu_id) {
            if ($mu_id <> '') {
                $idList[] = (int) $mu_id;
            }
        }
        $idListText = implode(',', $idList);

        $db = ezcDbInstance::get();
        $db->beginTransaction();
        $prId = $db->query("SELECT pr_id FROM ecogis.municipality WHERE mu_id={$idList[0]}")->fetchColumn();
        if ($this->act == 'add') {
            $sql = "INSERT INTO ecogis.municipality(do_id, pr_id, mu_istat, mu_name_1, mu_name_2, mu_type, the_geom)
                    SELECT :do_id, :pr_id, '00000000',  :mu_name_1, :mu_name_2, 'C', st_multi(st_union(the_geom)) 
                    FROM ecogis.municipality
                    WHERE do_id=:do_id AND mu_id IN ({$idListText})";
            $stmt = $db->prepare($sql);
            $stmt->execute(array('do_id' => (int) $request['do_id_collection'], 'pr_id' => $prId, 'mu_name_1' => $request['mu_name_1'], 'mu_name_2' => $request['mu_name_2']));
            $id = $db->lastInsertId('ecogis.municipality_mu_id_seq');
            $sql = "UPDATE ecogis.municipality SET mu_istat='C' || LPAD(mu_id::TEXT, 7, '0') WHERE mu_id={$id}";
            $db->exec($sql);
        } else {
            $id = (int) $this->id;
            // Save changes
            $sql = "UPDATE ecogis.municipality SET mu_name_1=:mu_name_1, mu_name_2=:mu_name_2 WHERE do_id=:do_id AND mu_id=:mu_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(array('do_id' => (int) $request['do_id_collection'], 'mu_id' => $id, 'mu_name_1' => $request['mu_name_1'], 'mu_name_2' => $request['mu_name_2']));
            // Remove old selection
            $sql = "UPDATE ecogis.municipality SET mu_parent_id=NULL WHERE do_id=:do_id AND mu_parent_id=:mu_parent_id";
            $stmt = $db->prepare($sql);
            $stmt->execute(array('do_id' => (int) $request['do_id_collection'], 'mu_parent_id' => $id));
        }
        $sql = "UPDATE ecogis.municipality SET mu_parent_id=:mu_parent_id WHERE do_id=:do_id AND mu_type='M' AND mu_id IN ({$idListText})";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('do_id' => (int) $request['do_id_collection'], 'mu_parent_id' => $id));

        // Update geometry
        $sql = "UPDATE ecogis.municipality SET the_geom=(
                  SELECT st_multi(st_union(the_geom)) 
                  FROM ecogis.municipality
                  WHERE mu_id IN ({$idListText}))
                WHERE mu_id=:mu_id AND do_id=:do_id AND mu_type='C'";
        $stmt = $db->prepare($sql);
        $stmt->execute(array('do_id' => (int) $request['do_id_collection'], 'mu_id' => $id));

        $db->commit();
        return array('status' => R3_AJAX_NO_ERROR,
            'js' => "submitFormDataDoneMunicipalityCollection($id)");
    }

    public function getAvailableMunicipalityForCollection($request) {

        return array('data' => R3EcoGisMunicipalityCollectionHelper::getAvailableMunicipalityList((int) $request['do_id_collection'], array('mu_name' => $request['mu_name'])));
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function confirmDeleteMunicipalityCollection($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = (int) $request['id'];
        $name = $db->query("SELECT mu_name_$lang FROM municipality_collection_data WHERE mu_id={$id}")->fetchColumn();
        return array('status' => R3_AJAX_NO_ERROR,
            'confirm' => sprintf(_("Sei sicuro di voler cancellare il raggruppamento \"%s\"?"), $name));
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
