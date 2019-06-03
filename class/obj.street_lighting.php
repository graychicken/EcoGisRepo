<?php

class eco_street_lighting extends R3AppBaseObject {

    /**
     * Field definition
     */
    protected $fields;

    /**
     * Table
     */
    protected $table = 'street_lighting';

    /**
     * ecogis.street_lighting fields definition
     */
    protected function defFields() {
        $fields = array(
            array('name' => 'sl_id', 'type' => 'integer', 'label' => _('PK'), 'is_primary_key' => true),
            array('name' => 'st_id', 'type' => 'lookup', 'required' => true, 'label' => _('Via'), 'lookup' => array('table' => 'common.street')),
            array('name' => 'sl_descr_1', 'type' => 'text', 'label' => _('Descrizione tratto')),
            array('name' => 'sl_descr_2', 'type' => 'text', 'label' => _('Descrizione tratto')),
            array('name' => 'sl_length', 'type' => 'float', 'label' => _('Lunghezza tratto')),
            array('name' => 'sl_to_check', 'type' => 'boolean', 'label' => _('Da controllare'), 'default' => 'false'),
            array('name' => 'sl_text_1', 'type' => 'text', 'label' => _('Note')),
            array('name' => 'sl_text_2', 'type' => 'text', 'label' => _('Note')),
        );
        return $fields;
    }

    /**
     * Return the fields for the list table
     */
    public function getTableColumnConfig() {
        $showMunicipality = R3EcoGisHelper::getMunicipalityCount($this->do_id) > 1;
        $rows = array('mu_name' => array('label' => _('Comune'), 'visible' => $showMunicipality),
            'sl_full_name' => array('label' => _('Tratto')),
            'sl_length' => array('label' => _('Lunghezza'), 'width' => 100, 'type' => 'float', 'options' => array('order_fields' => 'sl_length, sl_full_name, sl_id', 'number_format' => array('decimals' => 2))),
            'sl_to_check' => array('label' => _('Da controllare'), 'width' => 100, 'visible' => false, 'options' => array('align' => 'center', 'order_fields' => 'sl_to_check, sl_full_name, sl_id')),
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
        if (initVar('sl_id') !== null) {
            $this->id = initVar('sl_id');
        }
        $this->act = initVar('act', 'list');
        $this->last_id = initVar('last_id');
        $this->parent_act = initVar('parent_act');
        $this->do_id = $_SESSION['do_id'];
        $this->pr_id = PageVar('pr_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_id = PageVar('mu_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->mu_name = PageVar('mu_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->st_id = PageVar('st_id', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->st_name = PageVar('st_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->sl_full_name = PageVar('sl_full_name', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->sl_to_check = PageVar('sl_to_check', null, $init | $reset, false, $this->baseName, $storeVar);
        $this->order = PageVar('order', '1A', $init, false, $this->baseName, $storeVar);
        $this->pg = PageVar('pg', 0, $init | $reset, false, $this->baseName, $storeVar);

        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));

        $this->registerAjaxFunction('getHelp');
        $this->registerAjaxFunction('getMunicipalityList');
        $this->registerAjaxFunction('getStreetList');
        $this->registerAjaxFunction('getStreetLength');
        $this->registerAjaxFunction('confirmDeleteStreetLighting');
        $this->registerAjaxFunction('submitFormData');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        switch ($this->request['act']) {
            case 'add': return _('Nuova illuminazione stradale');
            case 'mod': return _('Modifica illuminazione stradale');
            case 'show': return _('Visualizza illuminazione stradale');
            case 'list': return _('Illuminazione stradale');
        }
        return '';  // Unknown title
    }

    /**
     * Return the filter values (list form)
     */
    public function getFilterValues() {
        $filters = array();
        if ($this->auth->getParam('mu_id') == '') {
            $filters['pr_values'] = R3EcoGisHelper::getProvinceList($this->do_id, array('join_with_street_lighting' => true));
            $filters['mu_values'] = R3EcoGisHelper::getMunicipalityList($this->do_id, null, null, array('join_with_street_lighting' => true));
        } else {
            $filters['mu_values'] = array($this->auth->getParam('mu_id') => '');
        }
        if (count($filters['mu_values']) == 1) {
            $mu_id = key($filters['mu_values']);
            $filters['st_values'] = R3EcoGisHelper::getStreetList($this->do_id, $mu_id, array('used_by' => 'street_lighting', 'use_lkp_name' => true));
        } else {
            $mu_id = null;
        }
        $filters['do_id'] = $this->auth->getDomainID();
        $filters['pr_id'] = $this->pr_id;
        $filters['mu_id'] = $this->mu_id;
        $filters['mu_name'] = $this->mu_name;
        $filters['st_id'] = $this->st_id;
        $filters['st_name'] = $this->st_name;
        $filters['sl_full_name'] = $this->sl_full_name;
        $filters['sl_to_check'] = $this->sl_to_check;
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
        if ($this->st_id <> '') {
            $where[] = $q->expr->eq('st_id', $db->quote((int) $this->st_id));
        }
        if ($this->st_name <> '') {
            $where[] = "st_name_{$lang} ILIKE " . $db->quote("%{$this->st_name}%");
        }
        if ($this->sl_full_name <> '') {
            $where[] = "sl_full_name_{$lang} ILIKE " . $db->quote("%{$this->sl_full_name}%");
        }
        $q->select("sl_id, cus_name_{$lang} AS cus_name, mu_name_{$lang} AS mu_name, sl_full_name_{$lang} AS sl_full_name, sl_length, 
                   CASE WHEN sl_to_check IN ('T', 'TRUE') THEN 'X' END AS sl_to_check, has_geometry, im_id")
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

        $tableConfig = new R3BaseTableConfig(R3AuthInstance::get());
        $tableColumns = $tableConfig->getConfig($this->getTableColumnConfig(), $this->baseName);
        foreach ($tableColumns as $fieldName => $colDef) {
            if ($colDef['visible']) {
                $this->simpleTable->addSimpleField($colDef['label'], $fieldName, $colDef['type'], $colDef['width'], $colDef['options']);
            }
        }
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 85);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order, 'street_lighting_list_table');
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
                        if ($row['has_geometry']) {
                            if (defined('GISCLIENT') && GISCLIENT == true) {
                                $links['MAP'] = $this->simpleTable->AddLinkCell(_('Visualizza su mappa'), "javascript:$.fn.zoomToMap({obj_t: 'street_lighting', obj_key: 'sl_id', obj_id: {$id}, highlight: true, windowMode: false, featureType: 'g_street_lighting.street_lighting'});", "", "{$this->imagePath}ico_map.gif");
                            } else {
                                $links['MAP'] = $this->simpleTable->AddLinkCell(_('Visualizza su mappa'), "javascript:showObjectOnMap('$id')", "", "{$this->imagePath}ico_map.gif");
                            }
                        } else {
                            $links['MAP'] = $this->simpleTable->AddLinkCell('', '', '', "../images/ico_spacer.gif");
                        }
                        $links['SHOW'] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:showObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'mod':
                        $links['MOD'] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modObject('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'del':
                        $links['DEL'] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelStreetLighting('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                }
            }
        }
        return $links;
    }

    public function getListTableRowStyle(&$row) {
        if ($row['sl_id'] == $this->last_id) {
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

            if ($vlu['has_geometry']) {
                $vlu['map_preview_url'] = R3EcoGisHelper::getMapPreviewURL($this->do_id, 'street_lighting', $this->id, $lang);
            }

            $vlu = array_merge($vlu, R3EcoGisHelper::getChangeLogData('street_lighting', $vlu['sl_id']));
        } else {
            $vlu = array();
            $vlu['do_id'] = $_SESSION['do_id'];
            $vlu['cus_name'] = R3EcoGisHelper::getDomainName($_SESSION['do_id']);
            $mu_values = R3EcoGisHelper::getMunicipalityList($this->do_id);
            if (count($mu_values) == 1) {
                $vlu['mu_id'] = key($mu_values);
            } else if ($this->auth->getParam('mu_id') > 0) {
                $vlu['mu_id'] = $this->auth->getParam('mu_id');
            }
            if (isset($vlu['mu_id']) && $vlu['mu_id'] <> '') {
                $vlu['mu_name'] = R3EcoGisHelper::getMunicipalityName($vlu['mu_id']);
            }
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
            $lkp['st_values'] = R3EcogisHelper::getStreetList($this->do_id, $mu_id);
            $lkp['cm_values'] = R3EcogisHelper::getCatMunicList($this->do_id, $mu_id);
        }

        if ($this->act == 'add' && count($lkp['mu_values']) == 1) {
            $mu_id = key($lkp['mu_values']);
        } else if ($this->act == 'mod') {
            $mu_id = $this->data['mu_id'];
        }
        if ($mu_id !== null) {
            $lkp['st_values'] = R3EcogisHelper::getStreetList($_SESSION['do_id'], $mu_id);
        }
        return $lkp;
    }

    public function getPageVars() {
        $tabMode = 'iframe';

        $tabs = array();
        $tabs[] = array('id' => 'consumption', 'label' => _('Consumi energia'), 'url' => "list.php?on=street_lighting_consumption&sl_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode&init");
        $tabs[] = array('id' => 'doc', 'label' => _('Documenti'), 'url' => "list.php?on=document&type=street_lighting&doc_object_id={$this->id}&parent_act={$this->act}&tab_mode=$tabMode");

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
        return $this->includeJS(array($this->baseName . '.js',
                    'mapopenfunc.js'), $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        $mapres = explode('x', $this->auth->getConfigValue('SETTINGS', 'MAP_RES', '1024x768'));
        return array('txtNewStreet' => _('Aggiungi indirizzo'),
            'UserMapWidth' => $mapres[0],
            'UserMapHeight' => $mapres[1],
            'PopupErrorMsg' => _('ATTENZIONE!\n\nBlocco dei popup attivo. Impossibile aprire la mappa. Disabilitare il blocco dei popup del browser e riprovare'),
            'MapFileName' => '../map/index.php',
            'askReplaceLength' => _('Si desidera aggiornare la lunghezza del tratto di strada con il nuovo valore?'),
            'MapName' => 'ECOGIS');
    }

    /**
     * Ajax request to submit data
     * @param array $request   the request
     * @return array           ajax format status
     */
    public function submitFormData($request) {
        $errors = array();
        $db = ezcDbInstance::get();

        if ($this->act <> 'add') {
            // Security check
            //R3Security::checkStreetLighting(@$request['id']);
        }
        $request['sl_id'] = $request['id'];

        if ($this->act <> 'del') {
            $request['mu_id'] = $this->checkFormDataForMunicipality($request, $errors);
            $errors = $this->checkFormData($request, $errors);
        }
        if (count($errors) > 0) {
            return $this->getAjaxErrorResult($errors);
        } else {
            $db->beginTransaction();
            $id = $this->applyData($request);
            if (isset($request['geometryStatus']) && strtoupper($request['geometryStatus']) == 'CHANGED') {
                $session_id = session_id();
                $sql = "UPDATE street_lighting
                        SET the_geom=foo.the_geom
                        FROM (SELECT ST_Multi(ST_Force_2d(ST_union(ST_Buffer(the_geom, 0.0)))) AS the_geom FROM edit_tmp_polygon WHERE session_id='{$session_id}') AS foo
                        WHERE sl_id=$id";
                $db->exec($sql);
                // Remove cache
                $sql = "UPDATE cache SET ca_expire_time=CURRENT_TIMESTAMP
                        WHERE ca_object_id=$id AND cat_id=2";
                $db->exec($sql);
            }
            $db->commit();
            R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
            R3EcoGisCacheHelper::resetMapPreviewCache(null);
            return array('status' => R3_AJAX_NO_ERROR,
                'js' => "submitFormDataDoneStreetLighting($id)");
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

    // Autocomplete comune
    public function getMunicipalityList($request) {
        $like = isset($request['term']) ? $request['term'] : null;
        $limit = isset($request['limit']) ? $request['limit'] : null;
        return R3EcoGisHelper::getMunicipalityList($_SESSION['do_id'], $like, $limit);
    }

    public function getStreetList($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $like = isset($request['term']) ? $request['term'] : null;
        $limit = isset($request['limit']) ? $request['limit'] : null;
        if (isset($request['mu_name']))
            $request['mu_id'] = (int) $db->query("SELECT mu_id FROM municipality WHERE mu_name_{$lang} ILIKE " . $db->quote($request['mu_name']))->fetchColumn();
        if (isset($request['autocomplete']))
            return R3EcogisHelper::getStreetList($_SESSION['do_id'], $request['mu_id'], array('like' => $like, 'limit' => $limit, 'allow_empty' => false));
        return array('status' => R3_AJAX_NO_ERROR,
            'data' => R3EcoGisHelper::forceJSonArray(R3EcogisHelper::getStreetList($_SESSION['do_id'], $request['mu_id'], array('allow_empty' => true))));
    }

    public function getStreetLength($request) {
        $db = ezcDbInstance::get();
        $streetLightingTableDef = $this->auth->getConfigValue('APPLICATION', 'STREET_LIGHTING_TABLE');
        $buffer = isset($streetLightingTableDef['buffer']) ? $streetLightingTableDef['buffer'] : 0;
        if (isset($request['useTempGeometry']) && $request['useTempGeometry'] == 'T') {
            $session_id = session_id();
            $perimeter = $db->query("SELECT st_perimeter(ST_Multi(ST_Force_2d(ST_union(the_geom)))) FROM edit_tmp_polygon WHERE session_id='{$session_id}'")->fetchColumn();
        } else {
            $perimeter = $db->query("SELECT st_perimeter(the_geom) FROM street_lighting WHERE sl_id=" . (int) $request['id'])->fetchColumn();
        }
        $length = max(round(($perimeter / 2) - (4 * $buffer), 2), 0);
        return array('status' => R3_AJAX_NO_ERROR,
            'length' => $length);
    }

    /**
     * Return the UDM
     * @param array $request    the request
     * @return array            the result data
     */
    public function confirmDeleteStreetLighting($request) {
        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();
        $id = (int) $request['id'];

        $name = $db->query("SELECT sl_full_name_$lang AS sw_title FROM street_lighting_data WHERE sl_id={$id}")->fetchColumn();
        $hasEnergyMeter = R3EcoGisHelper::hasEnergyMeter('STREET_LIGHTING', $id);
        $hasDocument = R3EcoGisHelper::hasDocument('STREET_LIGHTING', $id);
        if (!$hasEnergyMeter && !$hasDocument && $this->tryDeleteData($id)) {
            return array('status' => R3_AJAX_NO_ERROR,
                'confirm' => sprintf(_('Sei sicuro di voler cancellare il tratto "%s"?'), $name));
        }
        return array('status' => R3_AJAX_NO_ERROR,
            'alert' => sprintf(_('Impossibile cancellare il tratto "%s", poichè vi sono dei dati ad esso associati'), $name));
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

    function hasDialogMap() {
        return $this->act == 'list';  // dialog map only on list
    }

    public function getFeatureType() {
        return 'g_street_lighting.street_lighting';
    }

    public function getPrimaryKey() {
        return 'sl_id';
    }

}
