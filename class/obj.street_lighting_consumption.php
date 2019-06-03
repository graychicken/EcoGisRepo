<?php

class eco_street_lighting_consumption extends R3AppBaseObject {

    public function __construct(array $request = array(), array $opt = array()) {
        parent::__construct($request, $opt);

        $init = array_key_exists('init', $this->request);
        $this->act = initVar('act', 'list');
        $this->sl_id = PageVar('sl_id');
        $this->last_id = initVar('consumption_last_id');
        $this->parent_act = PageVar('parent_act');
        $this->tab_mode = initVar('tab_mode');
        $this->order = PageVar('order', '6A', $init, false, $this->baseName);
        setLang(R3Locale::getLanguageCode());
        setLangInfo(array('thousands_sep' => "."));
    }

    /**
     * Return the page title
     */
    public function getPageTitle() {
        return _('Illuminazione stradale - Consumi');
    }

    /**
     * Return the sql to generate the list
     */
    public function getListSQL() {

        $db = ezcDbInstance::get();
        $lang = R3Locale::getLanguageID();

        $q = $db->createSelectQuery();
        $where = "emo_code='STREET_LIGHTING' AND em_object_id=" . (int) $this->sl_id;
        $q->select("em_object_id, co_id, co_start_date, co_end_date, co_value, co_value_tep, co_value_kwh, co_value_co2, co_bill, co_bill_specific")
                ->from('consumption_data')
                ->where($where);
        return $q;
    }

    /**
     * Create the table header
     * param string $order             The table order
     */
    public function createListTableHeader(&$order) {
        $this->simpleTable->addSimpleField(_('Consumo') . " [" . _('kWh') . "]", 'co_value', 'float', null, array('sortable' => true, 'order_fields' => 'co_value, co_start_date', 'number_format' => array('decimals' => 2)));
        $this->simpleTable->addSimpleField(_('Consumo') . " [" . _('Tep eq.') . "]", 'co_value_tep', 'number', null, array('sortable' => true, 'order_fields' => 'co_value_tep, co_start_date', 'number_format' => array('decimals' => 2)));
        $this->simpleTable->addSimpleField(_('CO<sub>2</sub> emessa') . " [" . _('kg') . "]", 'co_value_co2', 'number', null, array('sortable' => true, 'order_fields' => 'co_value_co2, co_start_date', 'number_format' => array('decimals' => 2)));
        $this->simpleTable->addSimpleField(_('Spesa') . " [" . _('€') . "]", 'co_bill', 'number', null, array('sortable' => true, 'order_fields' => 'co_bill, co_start_date', 'number_format' => array('decimals' => 2)));
        $this->simpleTable->addSimpleField(_('Prezzo unitario') . " [" . _('€/kWh') . "]", 'co_bill_specific', 'number', null, array('sortable' => true, 'number_format' => array('decimals' => 2), 'order_fields' => 'co_bill_specific, co_start_date'));
        $this->simpleTable->addSimpleField(_('Data inizio'), 'co_start_date', 'date', 80, array('sortable' => true, 'align' => 'center', 'order_fields' => 'co_start_date, co_end_date'));
        $this->simpleTable->addSimpleField(_('Data fine'), 'co_end_date', 'date', 80, array('sortable' => true, 'align' => 'center', 'order_fields' => 'co_end_date, co_start_date'));
        $this->simpleTable->addSimpleField(_('Azione'), '', 'link', 85);
        $this->tableHtml = $this->simpleTable->CreateTableHeader($order);
    }

    public function getListTableRowOperations(&$row) {
        $id = $row['co_id'];
        $baseURL = 'edit.php?on=' . $this->baseName;
        $objName = strToUpper($this->baseName);
        $parent_act = $this->parent_act == 'show' ? $this->parent_act : 'mod';
        $links = array();
        foreach (array('show', 'mod', 'del') as $act) {
            if ($this->auth->hasPerm(strToUpper($act), $objName)) {
                switch ($act) {
                    case 'show':
                        $links[] = $this->simpleTable->AddLinkCell(_('Visualizza'), "javascript:showConsumption('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        break;
                    case 'mod':
                        if ($this->parent_act <> 'show') {
                            $links[] = $this->simpleTable->AddLinkCell(_('Modifica'), "javascript:modConsumption('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        }
                        break;
                    case 'del':
                        if ($this->parent_act <> 'show') {
                            $links[] = $this->simpleTable->AddLinkCell(_('Cancella'), "javascript:askDelConsumption('$id')", "", "{$this->imagePath}ico_{$act}.gif");
                        }
                        break;
                }
            }
        }
        return $links;
    }

    public function getListTableRowStyle(&$row) {
        if ($row['co_id'] == $this->last_id) {
            return array('normal' => 'selected_row');
        }
        return array();
    }

    public function getData($id = null) {
        
    }

    public function getPageVars() {
        return array('sl_id' => $this->sl_id,
            'tab_mode' => $this->tab_mode,
            'parent_act' => $this->parent_act,
            'date_format' => R3Locale::getJQueryDateFormat());
    }

    public function getJSFiles() {
        return $this->includeJS($this->baseName . '.js', $this->auth->getConfigValue('APPLICATION', 'INLINE_JS') == 'T');
    }

    public function getJSVars() {
        return array('txtAddConsumption' => _('Aggiungi consumo tratto stradale'),
            'txtModConsumption' => _('Modifica consumo tratto stradale'),
            'txtShowConsumption' => _('Mostra consumo tratto stradale'));
    }

    public function checkPerm() {
        $act = $this->act == 'list' ? 'SHOW' : strToUpper($this->act);
        $name = strToUpper($this->baseName);
        if (!$this->auth->hasPerm($act, $name)) {
            die(sprintf(_("PERMISSION DENIED [%s/%s]"), $act, $name));
        }
    }
}
