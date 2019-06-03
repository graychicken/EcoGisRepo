<?php

/**
 * Utility function class for EcoGIS
 */
class R3EcoGisUtilityHelper {

    // Restituisce i comuni associati ad un certo fornitore di servizi
    static public function getGlobalEnergySourceList() {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        $sql = "SELECT ges_id, ges_name_{$lang} AS ges_name
                FROM global_energy_source
                ORDER BY ges_name, ges_id";
        return $db->query($sql)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
    }

    // Restituisce i comuni associati ad un certo fornitore di servizi
    static public function getSelectedMunicipalityList($us_id) {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        $us_id = (int) $us_id;
        $sql = "SELECT mu.mu_id, mu_name_{$lang} AS mu_name
                FROM municipality mu
                INNER JOIN utility_supplier_municipality usm ON mu.mu_id=usm.mu_id
                INNER JOIN utility_supplier us ON us.us_id=usm.us_id
                WHERE us.us_id={$us_id}
                ORDER BY mu_name, mu_id";
        return $db->query($sql)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
    }

    // Restituisce i comuni non che non hanno un determinato fornitore di servizio
    static public function getAvailableMunicipalityList($do_id, $us_id) {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        $us_id = (int) $us_id;
        $do_id = (int) $do_id;
        $sql = "SELECT mu.mu_id, mu_name_{$lang} AS mu_name
                FROM municipality mu
                WHERE do_id={$do_id} AND 
                      mu_id NOT IN (SELECT mu_id FROM utility_supplier_municipality WHERE us_id={$us_id})
                ORDER BY mu_name_{$lang}, mu.mu_id";
        return $db->query($sql)->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE);
    }

    // Restituisce i comuni non che non hanno un determinato fornitore di servizio
    static public function getProductsList($us_id) {
        $db = ezcDbInstance::get();
        $us_id = (int) $us_id;
        $sql = "SELECT COUNT(em_id) AS tot, up.up_id, up_name_1, up_name_2, up_order, esu.esu_id, es.es_id, udm_id, esu_co2_factor, ges_id, et_code
                FROM utility_product up
                INNER JOIN energy_source_udm esu ON up.esu_id=esu.esu_id
                INNER JOIN energy_source es ON esu.es_id=es.es_id
                INNER JOIN energy_type et ON es.et_id=et.et_id
                LEFT JOIN energy_meter em ON esu.esu_id=em.esu_id
                WHERE us_id={$us_id}
                GROUP BY up.up_id, up_name_1, up_name_2, up_order, esu.esu_id, es.es_id, udm_id, esu_co2_factor, ges_id, et_code
                ORDER BY up_order, up_id";
        // echo $sql;
        $result = array();
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            $result[$row['up_id']] = $row;
        }
        return $result;
    }

    /**
     * Assegna i comuni. TODO: Verificare che non vi siano consumi associati
     */
    static public function setMunicipality($us_id, $municipality) {
        $db = ezcDbInstance::get();
        $us_id = (int) $us_id;
        $sql = "DELETE FROM utility_supplier_municipality 
                WHERE us_id={$us_id}";
        $db->exec($sql);
        $data = array();
        foreach ($municipality as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $sql = "INSERT INTO utility_supplier_municipality 
                        (us_id, mu_id) VALUES ($us_id, $id)";
                $db->exec($sql);
            }
        }
    }

    static public function getProductId($us_id) {
        $db = ezcDbInstance::get();
        $us_id = (int) $us_id;
        return $db->query("SELECT up_id FROM utility_product WHERE us_id={$us_id}")->fetchAll(PDO::FETCH_COLUMN | PDO::FETCH_UNIQUE, 0);
    }

    // Restituisce es_id e udm_id      
    static public function getEnergySourceAndUdm($do_id, $et_code) {
        return R3EcoGisHelper::getEnergySourceAndUdm($do_id, $et_code);
    }

    static public function updateProduct($data) {
        $db = ezcDbInstance::get();

        $esu_id = (int) $db->query("SELECT esu_id FROM utility_product WHERE up_id=" . (int) $data['up_id'])->fetchColumn();

        $sql = "UPDATE utility_product SET " .
                "up_name_1=" . $db->quote($data['up_name_1']) . ", " .
                "up_name_2=" . ($data['up_name_2'] == '' ? 'NULL' : $db->quote($data['up_name_2'])) . ", " .
                "up_order=" . (int) $data['up_order'] . " " .
                "WHERE up_id=" . (int) $data['up_id'];
        $db->exec($sql);
        $sql = "UPDATE energy_source_udm SET " .
                "esu_co2_factor=" . (float) forceFloat($data['esu_co2_factor'], null, '.') . ", " .
                "ges_id=" . ($data['ges_id'] == '' ? 'NULL' : (int) $data['ges_id']) . " " .
                "WHERE esu_id={$esu_id}";
        $db->exec($sql);
    }

    static public function addProduct($data) {
        $db = ezcDbInstance::get();

        // Aggiungo fattore di conversione
        list($es_id, $udm_id) = array_values(self::getEnergySourceAndUdm($data['do_id'], $data['et_code']));
        $fields = array('es_id' => (int) $es_id, 'udm_id' => (int) $udm_id, 'esu_kwh_factor' => 1, 'esu_co2_factor' => (float) forceFloat($data['esu_co2_factor'], null, '.'),
            'do_id' => (int) $data['do_id'], 'esu_is_private' => 'TRUE', 'esu_is_consumption' => 'TRUE', 'esu_is_production' => 'FALSE');
        if ($data['ges_id'] > 0) {
            $fields['ges_id'] = (int) $data['ges_id'];
        }
        $sql = 'INSERT INTO energy_source_udm (' .
                implode(', ', array_keys($fields)) . ' ' .
                ') VALUES (' .
                implode(', ', array_values($fields)) . ')';
        $db->exec($sql);
        $esu_id = $db->lastInsertId('energy_source_udm_esu_id_seq');

        // Aggiungo prodotto
        $fields = array('us_id' => (int) $data['us_id'],
            'up_name_1' => $db->quote($data['up_name_1']),
            'up_name_2' => $data['up_name_2'] == '' ? 'NULL' : $db->quote($data['up_name_2']),
            'up_order' => (int) $data['up_order'],
            'esu_id' => $esu_id);
        $sql = 'INSERT INTO utility_product (' .
                implode(', ', array_keys($fields)) . ' ' .
                ') VALUES (' .
                implode(', ', array_values($fields)) . ')';
        $db->exec($sql);
        return $db->lastInsertId('utility_product_up_id_seq');
    }

    static public function deleteProduct($data) {
        $db = ezcDbInstance::get();

        $esu_id = (int) $db->query("SELECT esu_id FROM utility_product WHERE up_id=" . (int) $data['up_id'])->fetchColumn();
        $sql = "DELETE FROM utility_product WHERE up_id=" . (int) $data['up_id'];
        $db->exec($sql);
        $sql = "DELETE FROM energy_source_udm WHERE esu_id={$esu_id}";
        $db->exec($sql);
    }

}

class eco_utility extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'utility_supplier';

    /**
     * building fields definition
     */

    /**
     * ecogis.utility_supplier fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'us_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'us_name_1', 'type' => 'text', 'size' => 40, 'required' => true, 'label' => _('Fornitore')),
            array('name' => 'us_name_2', 'type' => 'text', 'size' => 40, 'required' => false, 'label' => _('Fornitore')),
            array('name' => 'us_order', 'type' => 'integer', 'required' => true, 'label' => _('Ordinamento')),
            array('name' => 'do_id', 'type' => 'lookup', 'required' => true, 'lookup' => array('table' => 'auth.domains')),
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        $this->fields = $this->defFields();

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->id = initVar('id');
        $this->last_id = initVar('last_id');
        $this->act = initVar('act', 'list');
        $this->do_id = $_SESSION['do_id'];  // PageVar('do_id',          $_SESSION['do_id'],    $init | $reset, false, $this->baseName);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName);
        $this->es_id = PageVar('es_id', null, $init | $reset, false, $this->baseName);
        $this->order = PageVar('order', '1A', $init, false, $this->baseName);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName);
        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('askDelUtility');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuovo fornitore di energia');
            case 'mod': return _('Modifica fornitore di energia');
            case 'show': return _('Visualizza fornitore di energia');
            case 'list': return _('Elenco fornitori di energia');
        }
        return '';  // Unknown title
    }

    /**
     * Return the filter values (list form)
     */
    public function getFilterValues() {
        $filters = array();
        if ($this->auth->getParam('mu_id') == '') {
            $filters['mu_values'] = R3EcoGisHelper::getMunicipalityList($this->do_id);
        } else {
            $filters['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        if (count($filters['mu_values']) == 1) {
            $mu_id = key($filters['mu_values']);
        } else {
            $mu_id = null;
        }
        $filters['do_id'] = $this->do_id;
        $filters['mu_id'] = $this->mu_id;
        $filters['mu_name'] = $this->mu_name;
        $filters['es_id'] = $this->es_id;
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
        $where[] = $q->expr->eq('us.do_id', $this->do_id);
        if (!$this->auth->hasPerm('SHOW', 'ALL_DOMAINS') && $this->auth->getParam('mu_id') <> '') {
            $where[] = $q->expr->eq('usp.mu_id', $db->quote((int) $this->auth->getParam('mu_id')));
        }
        if ($this->mu_id <> '') {
            $where[] = $q->expr->eq('usp.mu_id', $db->quote((int) $this->mu_id));
        }
        if ($this->mu_name <> '') {
            $where[] = "mu_name_{$lang} ILIKE " . $db->quote("%{$this->mu_name}%");
        }
        $q->select("us.us_id, us.us_name_{$lang} AS us_name, us.us_order, us.do_id, array_to_string(array_agg(DISTINCT up.up_name_{$lang}), ', '::text) AS up_name, " .
                        "array_to_string(array_agg(DISTINCT mu.mu_name_{$lang}), ', '::text) AS mu_name")
                ->from('utility_supplier us')
                ->leftJoin('utility_product up', 'us.us_id=up.us_id')
                ->leftJoin('utility_supplier_municipality usp', 'usp.us_id=us.us_id')
                ->leftJoin('municipality mu', 'usp.mu_id=mu.mu_id');
        if (count($where) > 0) {
            $q->where($where);
        }
        $q->groupBy('us.us_id, us.us_name_1, us.us_name_2, us.us_order, us.do_id');
        return $q;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {

        if ($this->auth->getParam('mu_id') == '' && count(R3EcoGisHelper::getMunicipalityList($this->do_id)) > 1) {
            if ($this->mu_id == '') {
                $this->simpleTable->addSimpleField(_('Comuni'), 'mu_name', 'text', null, array('sortable' => true, 'order_fields' => 'mu_name'));
            }
        }
        $this->simpleTable->addSimpleField(_('Fornitore'), 'us_name', 'text', null, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Prodotti'), 'up_name', 'text', null, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Ordinamento'), 'us_order', 'integer', 80, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 85);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order, 'energy_source_list_table');
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['us_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'show':
                        $links['SHOW'] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:showObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'del':
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelUtility('$id')", "", "{$this->imagePath}ico_{$act}.gif");
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
        $db = ezcDbInstance::get();
        $id = (int) $id;
        $productMinEntry = 3;
        if ($this->act <> 'add') {
            $q = $db->createSelectQuery();
            $q->select('*')
                    ->from('utility_supplier')
                    ->where("us_id={$this->id}");
            $vlu = $db->query($q)->fetch(PDO::FETCH_ASSOC);
            $vlu['municipality'] = R3EcoGisUtilityHelper::getSelectedMunicipalityList($this->id);
            $vlu['products'] = R3EcoGisUtilityHelper::getProductsList($this->id);
            foreach ($vlu['products'] as $key => $val) {
                $vlu['products'][$key]['esu_co2_factor'] = R3NumberFormat($val['esu_co2_factor'], null, true);
            }
            $tot = max($productMinEntry - 1, count($vlu['products'])) + 1;
            $key = 0;
            for ($i = count($vlu['products']); $i < $tot; $i++) {
                $vlu['products']["new_{$key}"] = array();
                $key++;
            }
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('utility_supplier', $vlu['us_id']));
        } else {
            $vlu = array();
            $vlu['us_order'] = 0;
            $vlu['municipality'] = array();
            for ($i = 0; $i < $productMinEntry; $i++) {
                $vlu['products']["new_{$i}"] = array();
            }
        }
        $this->data = $vlu;
        return $vlu;
    }

    /**
     * Return the data for a single customer
     */
    public function getLookupData($id = null) {
        $lang = R3Locale::getLanguageID();
        $db = ezcDbInstance::get();
        $lkp = array();
        $lkp['kind_values'] = array('HEATING' => _('Riscaldamento'), 'ELECTRICITY' => _('Elettrico'));
        $lkp['pr_list'] = R3EcoGisHelper::getProvinceList($this->do_id);
        $lkp['mu_list'] = R3EcoGisUtilityHelper::getAvailableMunicipalityList($this->do_id, $this->id);
        $lkp['mu_selected'] = $this->data['municipality'];
        $lkp['ges_values'] = R3EcoGisUtilityHelper::getGlobalEnergySourceList();
        return $lkp;
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        return array('txtCantDeleteProduct' => _('Non è stato possibile eliminare alcuni prodotti, in quanto sono utilizzati'));
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();
        $db = ezcDbInstance::get();
        $request['us_id'] = (int) $request['id'];
        $request['do_id'] = $this->do_id;
        if ($this->act <> 'del') {
            $errors = $this->checkFormData($request);
        }

        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $oldLocale = getLocale();
            setLocale(LC_ALL, 'C');
            $db->beginTransaction();

            $oldIds = R3EcoGisUtilityHelper::getProductId($request['us_id']);
            if ($this->act == 'del') {
                // Cancella comuni vecchi
                R3EcoGisUtilityHelper::setMunicipality($request['us_id'], array());
                foreach ($db->query("SELECT up_id FROM utility_product WHERE us_id=" . (int) $request['us_id']) as $row) {
                    R3EcoGisUtilityHelper::deleteProduct(array('up_id' => $row['up_id']));
                }
            }
            $id = $this->applyData($request);
            $newIds = array(0, 1, 2);  // valori nuovo
            $cantDelete = 'F';
            if ($this->act == 'add' || $this->act == 'mod') {
                $order = 0;
                R3EcoGisUtilityHelper::setMunicipality($id, explode(',', $request['municipality']));
                // Modifica valori vecchi
                foreach ($oldIds as $up_id) {
                    $order+=10;
                    if (isset($request["up_name_1_{$up_id}"]) && $request["up_name_1_{$up_id}"] <> '') {
                        R3EcoGisUtilityHelper::updateProduct(array('up_id' => $up_id,
                            'up_name_1' => $request["up_name_1_{$up_id}"],
                            'up_name_2' => @$request["up_name_2_{$up_id}"],
                            'esu_co2_factor' => $request["esu_co2_factor_{$up_id}"],
                            'ges_id' => $request["ges_id_{$up_id}"],
                            'up_order' => $order));
                    } else {
                        try {
                            R3EcoGisUtilityHelper::deleteProduct(array('up_id' => $up_id));
                        } catch (Exception $e) {
                            $cantDelete = 'T';
                        }
                    }
                }
                // Aggiunge valori nuovi
                foreach ($newIds as $up_id) {
                    // echo $request["up_name_1_new_{$up_id}"];
                    $order+=10;
                    if (isset($request["up_name_1_new_{$up_id}"]) && $request["up_name_1_new_{$up_id}"] <> '') {
                        R3EcoGisUtilityHelper::addProduct(array('do_id' => $this->do_id,
                            'us_id' => $id,
                            'up_name_1' => $request["up_name_1_new_{$up_id}"],
                            'up_name_2' => @$request["up_name_2_new_{$up_id}"],
                            'up_order' => $order,
                            'esu_co2_factor' => $request["esu_co2_factor_new_{$up_id}"],
                            'ges_id' => $request["ges_id_new_{$up_id}"],
                            'et_code' => $request["et_code_new_{$up_id}"]));
                    }
                }
            }

            $db->commit();
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            setLocale(LC_ALL, $oldLocale);
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataUtilityDone($id, '$cantDelete')");
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

    public function askDelUtility($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = forceInteger($request['id'], 0, false, '.');
        $sql = "SELECT COUNT(*)
                FROM utility_product up
                INNER JOIN energy_meter em ON em.up_id=up.up_id
                WHERE us_id={$id}";
        if ($db->query($sql)->fetchColumn() > 0) {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _('Impossibile cancellare questo produttore di servizi poichè vi sono dei dati ad esso legati'));
        } else {
            $name = $db->query("SELECT us_name_$lang FROM utility_supplier WHERE us_id={$id}")->fetchColumn();
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf(_("Sei sicuro di voler cancellare il produttore di servizi \"%s\"?"), $name));
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
