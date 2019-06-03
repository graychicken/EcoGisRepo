<?php

class R3EcoGisGlobalResultTableHelper {

    static public function getGlobalEnergySourceList($id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = (int) $id;
        if ($id > 0) {
            $sql = "SELECT ges.ges_id, COALESCE(get_name_$lang, '') || ' - ' || COALESCE(ges_name_$lang, '') AS ges_name, gest_id
                    FROM global_energy_source ges
                    INNER JOIN global_energy_type get ON ges.get_id=get.get_id
                    LEFT JOIN global_energy_source_type gest ON ges.ges_id=gest.ges_id AND gt_id=$id
                    ORDER BY gest_order, get_name_$lang, get.get_id, ges_name_$lang, ges_id";
        } else {
            $sql = "SELECT ges.ges_id, COALESCE(get_name_$lang, '') || ' - ' || COALESCE(ges_name_$lang, '') AS ges_name, NULL AS gest_id
                    FROM global_energy_source ges
                    INNER JOIN global_energy_type get ON ges.get_id=get.get_id
                    ORDER BY get_order, get_name_$lang, get.get_id, ges_name_$lang, ges_id";
        }
        $result = array('unselected' => array(), 'selected' => array());
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            if ($row['gest_id'] == '') {
                $result['unselected'][$row['ges_id']] = $row['ges_name'];
            } else {
                $result['selected'][$row['ges_id']] = $row['ges_name'];
            }
        }
        return $result;
    }

    static public function getGlobalCategoryList($id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = (int) $id;
        if ($id > 0) {
            $sql = "SELECT gc2.gc_id AS gc_id, COALESCE(gc1.gc_name_$lang, '') || ' - ' || COALESCE(gc2.gc_name_$lang, '') AS gc_name, gcat_id
                    FROM global_category gc1
                    INNER JOIN global_category gc2 ON gc2.gc_parent_id=gc1.gc_id
                    LEFT JOIN global_category_type gcat ON gc2.gc_id=gcat.gc_id AND gt_id=$id
                    ORDER BY gcat_order, gc1.gc_name_$lang, gc1.gc_id, gc2.gc_name_$lang, gc2.gc_id, gcat_id";
        } else {
            $sql = "SELECT gc2.gc_id as gc_id, COALESCE(gc1.gc_name_$lang, '') || ' - ' || COALESCE(gc2.gc_name_$lang, '') AS gc_name, NULL AS gcat_id
                    FROM global_category gc1
                    INNER JOIN global_category gc2 ON gc2.gc_parent_id=gc1.gc_id
                    ORDER BY gc2.gc_order, gc1.gc_order, gc_name, gc2.gc_id, gc1.gc_id";
        }
        $result = array('unselected' => array(), 'selected' => array());
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            if ($row['gcat_id'] == '') {
                $result['unselected'][$row['gc_id']] = $row['gc_name'];
            } else {
                $result['selected'][$row['gc_id']] = $row['gc_name'];
            }
        }
        return $result;
    }

    /**
     * Insert, update, delete the GlobalEnergySourceType
     */
    static public function setGlobalEnergySourceType($gt_id, $gest_list) {
        $db = ezcDbInstance::get();
        $old = array();
        $gt_id = (int) $gt_id;
        foreach ($db->query("SELECT ges_id FROM global_energy_source_type WHERE gt_id=" . (int) $gt_id) as $row)
            $old[$row['ges_id']] = $row['ges_id'];
        $order = 0;
        foreach ($gest_list as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $order++;
                if (isset($old[$id])) {
                    $sql = "UPDATE global_energy_source_type SET gest_order={$order} WHERE ges_id={$id} AND gt_id={$gt_id}";
                    $db->exec($sql);
                    unset($old[$id]);
                } else {
                    $sql = "INSERT INTO global_energy_source_type (ges_id, gt_id, gest_order) VALUES ({$id}, {$gt_id}, {$order})";
                    $db->exec($sql);
                }
            }
        }
        foreach ($old as $val) {
            $sql = "DELETE FROM global_energy_source_type WHERE ges_id={$val} AND gt_id={$gt_id}";
            $db->exec($sql);
        }
    }

    /**
     * Insert, update, delete the GlobalEnergySourceType
     */
    static public function setGlobalCategoryType($gt_id, $gcat_list) {
        $db = ezcDbInstance::get();
        $old = array();
        $gt_id = (int) $gt_id;
        foreach ($db->query("SELECT gc_id FROM global_category_type WHERE gt_id=" . (int) $gt_id) as $row)
            $old[$row['gc_id']] = $row['gc_id'];
        $order = 0;
        foreach ($gcat_list as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $order++;
                if (isset($old[$id])) {
                    $sql = "UPDATE global_category_type SET gcat_order={$order} WHERE gc_id={$id} AND gt_id={$gt_id}";
                    $db->exec($sql);
                    unset($old[$id]);
                } else {
                    $sql = "INSERT INTO global_category_type (gc_id, gt_id, gcat_order) VALUES ({$id}, {$gt_id}, {$order})";
                    $db->exec($sql);
                }
            }
        }
        foreach ($old as $val) {
            $sql = "DELETE FROM global_category_type WHERE gc_id={$val} AND gt_id={$gt_id}";
            $db->exec($sql);
        }
    }

}

class eco_global_result_table_builder extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'global_type';

    /**
     * ecogis.global_type fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'gt_id', 'type' => 'integer', 'is_primary_key' => true),
            array('name' => 'gt_code', 'type' => 'text', 'size' => 40, 'required' => true, 'label' => _('Codice')),
            array('name' => 'gt_name_1', 'type' => 'text', 'size' => 80, 'label' => _('Nome')),
            array('name' => 'gt_name_2', 'type' => 'text', 'size' => 80, 'label' => _('Nome')),
            array('name' => 'gt_name_3', 'type' => 'text', 'size' => 80, 'label' => _('Nome')),
        );
        return $fields;
    }

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);


        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->id = initVar('id');
        $this->act = initVar('act', 'list');
        $this->order = PageVar('order', '1A', $init, false, $this->baseName);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName);
        $this->fields = $this->defFields();

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('askDelGlobalResultTable');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuova tabelle inventario emissioni');
            case 'mod': return _('Modifica tabelle inventario emissioni');
            case 'list': return _('Tabelle inventario emissioni');
        }
        return '';  // Unknown title
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $sql = "select gt_id, gt_code, gt_name_1, gt_name_2, gt_name_3
                from global_type";
        return $sql;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {

        $this->simpleTable->addSimpleField(_('Codice'), 'gt_code', 'text', 150, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Nome') . getLangNameShort(1), 'gt_name_1', 'text', null, array('sortable' => true));
        if (R3AuthInstance::get()->getConfigValue('APPLICATION', 'NUM_LANGUAGES', 1) > 1) {
            $this->simpleTable->addSimpleField(_('Nome') . getLangNameShort(2), 'gt_name_2', 'text', null, array('sortable' => true));
        }
        $this->simpleTable->addSimpleField(_('Nome') . getLangNameShort(3), 'gt_name_3', 'text', null, array('sortable' => true));
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 50);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order);
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['gt_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "../images/ico_" . $act . ".gif");
                        break;
                    case 'del':
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelGlobalResultTable('$id')", "", "../images/ico_" . $act . ".gif");
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
        if ($this->act == 'add') {
            $vlu = array(); 
            $vlu['gt_id'] = null;
        } else {
            $sql = "SELECT *
                    FROM global_type
                    WHERE gt_id=" . $this->id;
            $vlu = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('global_type', $vlu['gt_id']));
        }
        $this->data = $vlu; // Save the data (prevent multiple sql)
        return $vlu;
    }

    /**
     * Return the data for a single customer 
     */
    public function getLookupData($id = null) {
        $lkp = array();
        $data = R3EcoGisGlobalResultTableHelper::getGlobalEnergySourceList($this->data['gt_id']);
        $lkp['ges_list'] = $data['unselected'];
        $lkp['gest_list'] = $data['selected'];

        $data = R3EcoGisGlobalResultTableHelper::getGlobalCategoryList($this->data['gt_id']);
        $lkp['gc_list'] = $data['unselected'];
        $lkp['gc_selected'] = $data['selected'];

        return $lkp;
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
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

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();
        $db = ezcDbInstance::get();

        $request['gt_id'] = $request['id'];
        if ($this->act <> 'del') {
            $request['gt_code'] = strtoupper($request['gt_code']);
            $errors = $this->checkFormData($request);
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $db->beginTransaction();
            $id = $this->applyData($request);
            if ($this->act <> 'del') {
                R3EcoGisGlobalResultTableHelper::setGlobalEnergySourceType($id, explode(',', $request['global_energy_source_type']));
                R3EcoGisGlobalResultTableHelper::setGlobalCategoryType($id, explode(',', $request['global_category']));
            }
            $db->commit();
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneGlobalResultTableBuilder($id)");
        }
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function askDelGlobalResultTable($request) {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = $request['id'];
        $name = $db->query("SELECT gt_name_$lang FROM global_type WHERE gt_id={$id}")->fetchColumn();
        if ($this->tryDeleteData($id)) {
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf(_("Sei sicuro di voler cancellare la tabella di inventario emissioni \"%s\"?"), $name));
        } else {
            return array('status' => R3_AJAX_NO_ERROR,
                'alert' => _('Impossibile cancellare questa tabella di inventario emissioni poichè vi sono dei dati ad essa collegati'));
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
