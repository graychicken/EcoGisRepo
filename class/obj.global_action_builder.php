<?php

class R3EcoGisGlobalActionHelper {

    static public function getGlobalActionList($id) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = (int) $id;
        $sql = "SELECT gpa.gpa_id, gc_id, gpa_name_$lang || COALESCE(' - ' || gpa_extradata_{$lang}, '') AS gpa_name
                FROM global_plain_action gpa
                LEFT JOIN global_plain_action_category gpac ON gpac.gpa_id=gpa.gpa_id AND gc_id=$id
                ORDER BY gpa_order, gpa_name, gpa.gpa_id";
        // echo $sql;
        $result = array('unselected' => array(), 'selected' => array());
        foreach ($db->query($sql, PDO::FETCH_ASSOC) as $row) {
            if ($row['gc_id'] == '') {
                $result['unselected'][$row['gpa_id']] = $row['gpa_name'];
            } else {
                $result['selected'][$row['gpa_id']] = $row['gpa_name'];
            }
        }
        return $result;
    }

    /**
     * Insert, update, delete the GlobalEnergySourceType
     */
    static public function setGlobalAction($gc_id, $gpac_list) {
        $db = ezcDbInstance::get();
        $old = array();
        $gc_id = (int) $gc_id;
        $sql = "DELETE FROM global_plain_action_category WHERE gc_id={$gc_id}";
        $db->query($sql);
        foreach ($gpac_list as $id) {
            $id = (int) $id;
            if ($id > 0) {
                $sql = "INSERT INTO global_plain_action_category (gc_id, gpa_id) VALUES ({$gc_id}, {$id})";
                $db->exec($sql);
            }
        }
    }

}

class eco_global_action_builder extends R3AppBaseObject {

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $init = array_key_exists('init', $this->request);
        $reset = array_key_exists('reset', $this->request);
        $this->id = initVar('id');
        $this->act = initVar('act', 'list');
        $this->order = PageVar('order', '1A', $init, false, $this->baseName);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName);

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('submitFormData');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'mod': return _('Modifica associazione azioni principali/categoria');
            case 'list': return _('Associazione azioni principali/categoria');
        }
        return '';
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $sql = "SELECT gc1.gc_id, gc2.gc_name_{$lang} AS gc_name_main, gc1.gc_name_{$lang} AS gc_name, array_to_string(array_agg(DISTINCT gpa_name_{$lang}), ', '::text) AS gpa_name
                FROM global_category gc1
                INNER JOIN global_category gc2 ON gc1.gc_parent_id=gc2.gc_id
                LEFT JOIN global_plain_action_category gpac ON gc1.gc_id=gpac.gc_id
                LEFT JOIN global_plain_action gpa on gpa.gpa_id=gpac.gpa_id
                GROUP BY gc1.gc_id, gc2.gc_id, gc1.gc_name_{$lang}, gc2.gc_name_{$lang}";
        return $sql;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {

        $this->simpleTable->addSimpleField(_('Macro-categoria'), 'gc_name_main', 'text', null, array('sortable' => true, 'order_fields' => 'gc_name_main, gc_name, gpa_name'));
        $this->simpleTable->addSimpleField(_('Categoria'), 'gc_name', 'text', null, array('sortable' => true, 'order_fields' => 'gc_name, gc_name_main, gpa_name'));
        $this->simpleTable->addSimpleField(_('Azioni princiapli'), 'gpa_name', 'text', null, array('sortable' => true, 'order_fields' => 'gpa_name, gc_name_main, gc_name'));
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 50);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order);
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['gc_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $links = array();
        foreach (array('mod') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "../images/ico_" . $act . ".gif");
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
        $sql = "SELECT gc1.gc_id, gc2.gc_name_{$lang} AS gc_name_main, gc1.gc_name_{$lang} AS gc_name
                FROM global_category gc1
                INNER JOIN global_category gc2 ON gc1.gc_parent_id=gc2.gc_id
                WHERE gc1.gc_id=" . (int) $this->id;
        $vlu = $db->query($sql)->fetch(PDO::FETCH_ASSOC);
        $gpac_id = $db->query("SELECT gpac_id FROM ecogis.global_plain_action_category WHERE gc_id=$this->id ORDER BY gpac_id LIMIT 1")->fetchColumn(); // Ricavo il primo record per determinare la data di modifica
        $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('global_plain_action_category', $gpac_id));
        return $vlu;
    }

    /**
     * Return the data for a single customer 
     */
    public function getLookupData($id = null) {
        $lkp = array();
        $data = R3EcoGisGlobalActionHelper::getGlobalActionList($this->id);
        $lkp['gpa_id_available'] = $data['unselected'];
        $lkp['gpa_id_selected'] = $data['selected'];
        return $lkp;
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

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();
        $db = ezcDbInstance::get();
        $db->beginTransaction();
        $id = (int) $request['id'];
        R3EcoGisGlobalActionHelper::setGlobalAction($id, explode(',', $request['gpa_id_list']));
        $db->commit();
        R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
        return array('status' => R3_AJAX_NO_ERROR,
            'js' => "submitFormDataDoneGlobalActionBuilder($id)");
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
