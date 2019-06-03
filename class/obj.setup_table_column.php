<?php

class eco_setup_table_column extends R3AppBaseObject {

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);
        $this->act = 'mod';

        $this->module = PageVar('module', null, false, false, $this->baseName);

        $this->registerAjaxFunction('submitFormData');
        $this->registerAjaxFunction('resetTableColumnToDefault');
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        return _('Modifica configurazione elenco');
    }

    public function getListSQL() {
        
    }

    public function createListTableHeader(&$order) {
        
    }

    public function getData($id = null) {

        // get data from class
        $obj = R3Controller::factory(array('on' => $this->module));

        $tableConfig = new R3BaseTableConfig(R3AuthInstance::get());
        $tableColumns = $tableConfig->getConfig($obj->getTableColumnConfig(), $this->module);
        $widthList = array(100, 200, 500);
        foreach ($tableColumns as $key => $col) {
            if ($tableColumns[$key]['width'] > 0 && !in_array($tableColumns[$key]['width'], $widthList)) {
                $widthList[] = $tableColumns[$key]['width'];
            }
            sort($widthList);
            $tableColumns[$key]['width_list'] = array_merge(array(null), $widthList);
        }
        return $tableColumns;
    }

    public function getLookupData($id = null) {
        return array('yesno' => array(true => _('Si'), false => _('No')));
    }

    public function getPageVars() {
        return array('module' => $this->module);
    }

    public function getJSVars() {
        return array('page_title' => $this->getPageTitle(),
            'txtResetToDefault' => _('Ripristinare i valori di default?'));
    }

    public function submitFormData($request) {
        $params = array('label', 'visible', 'width');

        // get data from class
        $obj = R3Controller::factory(array('on' => $this->module));
        $tableConfig = new R3BaseTableConfig(R3AuthInstance::get());
        $defaultConfig = $obj->getTableColumnConfig();
        $config = array();
        $positions = explode(',', $request['fields_position']);
        foreach ($defaultConfig as $key => $data) {
            foreach ($params as $param) {
                if (isset($request["{$key}_{$param}"])) {
                    if ($request["{$key}_{$param}"] == '' || ($param == 'width' && strtoupper($request["{$key}_{$param}"]) == 'AUTO')) {
                        $config[$key][$param] = null;
                    } else {
                        $config[$key][$param] = $request["{$key}_{$param}"];
                    }
                }
            }
        }
        foreach ($positions as $order => $key) {
            $config[$key]['position'] = $order + 1;
        }
        $tableConfig->setConfig($config, $this->module, $defaultConfig);
        R3EcoGisEventNotifier::notifyDataChanged($this, array('data_changed' => true));
        return array('status' => R3_AJAX_NO_ERROR,
            'js' => "submitFormDataDoneSetupTableColumn()");
    }

    public function resetTableColumnToDefault($result) {
        // get data from class
        $obj = R3Controller::factory(array('on' => $this->module));
        $tableConfig = new R3BaseTableConfig(R3AuthInstance::get());
        $tableConfig->resetConfig($this->module);

        return array('status' => R3_AJAX_NO_ERROR);
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }

}
